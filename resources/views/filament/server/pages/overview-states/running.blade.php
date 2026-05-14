@php
    /** @var \App\Models\Server $server */
    $playerCount = $this->playerCount;
    $playerLimit = $this->playerLimit;
    $uptime = $this->uptimeLabel();
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $diskLimit > 0 ? min(100, ($diskUsed / $diskLimit) * 100) : 0;
    $diskBarTone = $diskPct >= 85 ? 'danger' : ($diskPct >= 60 ? 'warning' : 'success');
@endphp

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid overview-stat-grid--3 grid grid-cols-3 gap-3">
    <div class="overview-stat-card">
        <p class="overview-stat-card__label">Players</p>
        <p class="overview-stat-card__value">
            @if ($playerCount !== null)
                {{ $playerCount }} / {{ $playerLimit ?? '—' }}
            @else
                <span class="overview-stat-card__placeholder">—</span>
            @endif
        </p>
    </div>

    <div class="overview-stat-card">
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
        <div class="overview-bar overview-bar--{{ $diskBarTone }}">
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
