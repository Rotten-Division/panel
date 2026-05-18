@php
    /** @var \App\Models\Server $server */
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $this->diskUsedPercent();
@endphp

<x-overview.state-banner
    variant="default"
    :title="trans('server/overview.stopped.title')"
    :subtitle="trans('server/overview.stopped.subtitle')"
    icon="tabler-player-pause-filled"
/>

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid grid grid-cols-1 md:grid-cols-3 gap-3 overview-stat-grid--muted">
    <x-overview.offline-card>
        <div class="overview-stat-card overview-stat-card--muted">
            <p class="overview-stat-card__label">Players</p>
            <p class="overview-stat-card__value">0 / 20</p>
        </div>
    </x-overview.offline-card>

    <x-overview.offline-card>
        <div class="overview-stat-card overview-stat-card--muted">
            <p class="overview-stat-card__label">Uptime</p>
            <p class="overview-stat-card__value">00:00:00</p>
        </div>
    </x-overview.offline-card>

    <div class="overview-stat-card overview-stat-card--with-bar">
        <p class="overview-stat-card__label">Disk</p>
        <p class="overview-stat-card__value">{{ number_format($diskUsed / 1024 / 1024 / 1024, 2) }} GiB</p>
        <p class="overview-stat-card__sub">of {{ $diskLimit > 0 ? number_format($diskLimit / 1024 / 1024 / 1024, 0) . ' GiB' : 'unlimited' }}</p>
        <div class="overview-bar overview-bar--{{ $this->diskBarTone() }}">
            <div class="overview-bar__fill" style="width: {{ number_format($diskPct, 1, '.', '') }}%"></div>
        </div>
    </div>
</div>

{{-- console: read-only --}}
<x-filament-widgets::widgets
    :columns="1"
    :data="$this->getWidgetData() + ['readOnly' => true]"
    :widgets="[\App\Filament\Server\Widgets\ServerConsole::class]"
/>

{{-- resource sparks blurred behind an offline chip — keep them mounted so
     the row animates back in cleanly the moment the server returns. --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <x-overview.offline-card>
        <x-overview.spark title="CPU" value="0%" muted />
    </x-overview.offline-card>
    <x-overview.offline-card>
        <x-overview.spark title="Memory" value="0 GiB" muted />
    </x-overview.offline-card>
    <x-overview.offline-card>
        <x-overview.spark title="Network" value="0 B/s" muted />
    </x-overview.offline-card>
</div>
