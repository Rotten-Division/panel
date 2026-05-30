@php
    /** @var \App\Models\Server $server */
    $playerCount = $this->playerCount;
    $playerLimit = $this->playerLimit;
    $uptime = $this->uptimeLabel();
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $this->diskUsedPercent();
@endphp

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid grid grid-cols-1 md:grid-cols-3 gap-3">
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
        <p class="overview-stat-card__sub">of {{ $diskLimit > 0 ? number_format($diskLimit / 1024 / 1024 / 1024, 0).' GiB' : 'unlimited' }}</p>
        <div class="overview-bar overview-bar--{{ $this->diskBarTone() }}">
            <div class="overview-bar__fill" style="width: {{ number_format($diskPct, 1, '.', '') }}%"></div>
        </div>
    </div>
</div>
