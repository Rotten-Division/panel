@php
    /** @var \App\Models\Server $server */
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $diskLimit > 0 ? min(100, ($diskUsed / $diskLimit) * 100) : 0;
    $diskBarTone = $diskPct >= 85 ? 'danger' : ($diskPct >= 60 ? 'warning' : 'success');
    $cards = [
        ['label' => 'Players', 'value' => null],
        ['label' => 'Uptime', 'value' => null],
        ['label' => 'World size', 'value' => null],
        ['label' => 'CPU', 'value' => null],
        ['label' => 'Memory', 'value' => null],
    ];
@endphp

<x-overview.state-banner
    variant="default"
    title="Server stopped"
    subtitle="Hit start to bring it online"
    icon="tabler-player-pause-filled"
/>

<div class="overview-stat-grid overview-stat-grid--6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 overview-stat-grid--muted">
    @foreach ($cards as $card)
        <div class="overview-stat-card overview-stat-card--muted">
            <p class="overview-stat-card__label">{{ $card['label'] }}</p>
            <p class="overview-stat-card__value">
                @if ($card['value'] === null)
                    <span class="overview-stat-card__placeholder">—</span>
                @else
                    {{ $card['value'] }}
                @endif
            </p>
        </div>
    @endforeach

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
