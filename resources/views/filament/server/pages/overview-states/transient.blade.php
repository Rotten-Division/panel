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

    // memory_bytes is the last cached sample; format as GiB. read cache
    // directly here rather than route through Overview::latestStatsValue,
    // which returns int and would truncate sub-GiB values.
    $memoryRaw = collect(cache()->get("servers.$server->id.memory_bytes") ?? [])->last();
    $memoryDisplay = is_numeric($memoryRaw) && $memoryRaw > 0
        ? number_format($memoryRaw / 1024 / 1024 / 1024, 2).' GiB'
        : null;
    $memorySub = $memoryDisplay !== null ? 'warming up' : null;
@endphp

<x-overview.state-banner
    variant="transient"
    :title="$transientCopy['title']"
    :subtitle="$transientCopy['subtitle']"
    icon="tabler-loader-2"
/>

<x-overview.progress-band />

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 overview-stat-grid--muted">
    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">Players</p>
        <p class="overview-stat-card__value"><span class="overview-stat-card__placeholder">—</span></p>
    </div>

    <div class="overview-stat-card">
        <p class="overview-stat-card__label">Uptime</p>
        <p class="overview-stat-card__value">{{ $transientCopy['uptime'] }}</p>
    </div>

    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">CPU load</p>
        <p class="overview-stat-card__value"><span class="overview-stat-card__placeholder">—</span></p>
    </div>

    <div class="overview-stat-card">
        <p class="overview-stat-card__label">Memory</p>
        <p class="overview-stat-card__value">
            @if ($memoryDisplay)
                {{ $memoryDisplay }}
            @else
                <span class="overview-stat-card__placeholder">—</span>
            @endif
        </p>
        @if ($memorySub)
            <p class="overview-stat-card__sub">{{ $memorySub }}</p>
        @endif
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

{{-- console: read-only (live stream, no input). resource cards stay until
     Phase 5b.4 Task 3 swaps the cards for a flat spark row. --}}
<x-filament-widgets::widgets
    :columns="1"
    :data="$this->getWidgetData() + ['readOnly' => true]"
    :widgets="[\App\Filament\Server\Widgets\ServerConsole::class]"
/>

<x-filament-widgets::widgets
    :columns="3"
    :data="$this->getWidgetData()"
    :widgets="[
        \App\Filament\Server\Widgets\ServerCpuChart::class,
        \App\Filament\Server\Widgets\ServerMemoryChart::class,
        \App\Filament\Server\Widgets\ServerNetworkChart::class,
    ]"
/>
