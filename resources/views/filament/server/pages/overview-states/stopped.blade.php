@php
    /** @var \App\Models\Server $server */
    $uptime = $this->uptimeLabel();
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $this->diskUsedPercent();
@endphp

<x-overview.state-banner
    variant="default"
    title="Server stopped"
    subtitle="Hit start to bring it online"
    icon="tabler-player-pause-filled"
/>

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid grid grid-cols-1 md:grid-cols-3 gap-3 overview-stat-grid--muted">
    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">Players</p>
        <p class="overview-stat-card__value">
            <span class="overview-stat-card__placeholder">—</span>
        </p>
    </div>

    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">Uptime</p>
        <p class="overview-stat-card__value">
            @if ($uptime)
                {{ $uptime }}
            @else
                <span class="overview-stat-card__placeholder">—</span>
            @endif
        </p>
    </div>

    <div class="overview-stat-card overview-stat-card--with-bar">
        <p class="overview-stat-card__label">Disk</p>
        <p class="overview-stat-card__value">{{ number_format($diskUsed / 1024 / 1024 / 1024, 2) }} GiB</p>
        <p class="overview-stat-card__sub">of {{ number_format($diskLimit / 1024 / 1024 / 1024, 0) }} GiB</p>
        <div class="overview-bar overview-bar--{{ $this->diskBarTone() }}">
            <div class="overview-bar__fill" style="width: {{ number_format($diskPct, 1, '.', '') }}%"></div>
        </div>
    </div>
</div>

<x-filament-widgets::widgets
    :columns="1"
    :data="$this->getWidgetData()"
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
