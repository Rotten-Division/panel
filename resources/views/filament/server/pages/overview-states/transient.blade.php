@php
    /** @var \App\Models\Server $server */
    /** @var \App\Enums\ContainerStatus|null $containerStatus */
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $this->diskUsedPercent();

    $transientKey = match (true) {
        $server->status === \App\Enums\ServerState::RestoringBackup => 'restoring_backup',
        ($containerStatus ?? null) === \App\Enums\ContainerStatus::Stopping => 'stopping',
        ($containerStatus ?? null) === \App\Enums\ContainerStatus::Restarting => 'restarting',
        default => 'starting',
    };
    $transientCopy = trans("server/overview.transient.$transientKey");
@endphp

<x-overview.state-banner
    variant="transient"
    :title="$transientCopy['title']"
    :subtitle="$transientCopy['subtitle']"
    icon="tabler-loader-2"
/>

<x-overview.progress-band />

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid grid grid-cols-1 md:grid-cols-3 gap-3 overview-stat-grid--muted">
    <x-overview.offline-card label="Awaiting Server">
        <div class="overview-stat-card overview-stat-card--muted">
            <p class="overview-stat-card__label">Players</p>
            <p class="overview-stat-card__value">0 / 20</p>
        </div>
    </x-overview.offline-card>

    <div class="overview-stat-card">
        <p class="overview-stat-card__label">Uptime</p>
        <p class="overview-stat-card__value">{{ $transientCopy['uptime'] }}</p>
    </div>

    <div class="overview-stat-card overview-stat-card--with-bar">
        <p class="overview-stat-card__label">Disk</p>
        <p class="overview-stat-card__value">{{ number_format($diskUsed / 1024 / 1024 / 1024, 2) }} GiB</p>
        <p class="overview-stat-card__sub">of {{ $diskLimit > 0 ? number_format($diskLimit / 1024 / 1024 / 1024, 0).' GiB' : 'unlimited' }}</p>
        <div class="overview-bar overview-bar--{{ $this->diskBarTone() }}">
            <div class="overview-bar__fill" style="width: {{ number_format($diskPct, 1, '.', '') }}%"></div>
        </div>
    </div>
</div>

{{-- console: read-only (live stream, no input) --}}
<x-filament-widgets::widgets
    :columns="1"
    :data="$this->getWidgetData() + ['readOnly' => true]"
    :widgets="[\App\Filament\Server\Widgets\ServerConsole::class]"
/>

@php
    // read whichever stats wings has already pushed during boot. each
    // series gets normalised against its own max for the spark's 0..1
    // height-scaling so even tiny early samples render visibly.

    $cpuRaw = collect(cache()->get("servers.$server->id.cpu_absolute") ?? [])
        ->slice(-24)
        ->map(fn ($v) => (float) $v)
        ->values()
        ->all();
    $cpuMax = ! empty($cpuRaw) ? max($cpuRaw) : 0;
    $cpuNormalised = $cpuMax > 0
        ? array_map(fn ($v) => $v / $cpuMax, $cpuRaw)
        : [];
    $cpuValue = ! empty($cpuRaw) ? number_format(end($cpuRaw), 1).'%' : null;

    $memorySeries = collect(cache()->get("servers.$server->id.memory_bytes") ?? [])
        ->slice(-24)
        ->map(fn ($v) => (float) round($v / 1024 / 1024 / 1024, 2))
        ->values()
        ->all();
    $memoryMax = ! empty($memorySeries) ? max($memorySeries) : 0;
    $memoryNormalised = $memoryMax > 0
        ? array_map(fn ($v) => $v / $memoryMax, $memorySeries)
        : [];
    $memoryValue = ! empty($memorySeries) ? number_format(end($memorySeries), 2).' GiB' : null;

    // network samples are objects with cumulative rx_bytes/tx_bytes counters;
    // diff against the previous sample to get per-tick throughput (in + out).
    $networkRaw = collect(cache()->get("servers.$server->id.network") ?? [])
        ->slice(-25)
        ->values()
        ->all();
    $networkSeries = [];
    $previous = null;
    foreach ($networkRaw as $current) {
        if ($previous !== null) {
            $networkSeries[] = max(0, (int) ($current->rx_bytes - $previous->rx_bytes))
                + max(0, (int) ($current->tx_bytes - $previous->tx_bytes));
        }
        $previous = $current;
    }
    $networkMax = ! empty($networkSeries) ? max($networkSeries) : 0;
    $networkNormalised = $networkMax > 0
        ? array_map(fn ($v) => $v / $networkMax, $networkSeries)
        : [];
    $networkValue = ! empty($networkSeries)
        ? number_format(end($networkSeries) / 1024, 1).' KiB/s'
        : null;
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <x-overview.spark
        title="CPU"
        :value="$cpuValue"
        :series="$cpuNormalised"
        :muted="empty($cpuNormalised)"
    />
    <x-overview.spark
        title="Memory"
        :value="$memoryValue"
        :series="$memoryNormalised"
        color="moss"
        :muted="empty($memoryNormalised)"
    />
    <x-overview.spark
        title="Network"
        :value="$networkValue"
        :series="$networkNormalised"
        color="azure"
        :muted="empty($networkNormalised)"
    />
</div>
