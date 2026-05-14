@php
    /** @var \App\Models\Server $server */
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $diskPct = $diskLimit > 0 ? min(100, ($diskUsed / $diskLimit) * 100) : 0;
    $diskBarTone = $diskPct >= 85 ? 'danger' : ($diskPct >= 60 ? 'warning' : 'success');

    $eggName = $server->egg?->name ?? 'dependencies';
    $bannerVariant = match ($server->status) {
        \App\Enums\ServerState::InstallFailed, \App\Enums\ServerState::ReinstallFailed => 'suspended',
        default => 'installing',
    };
    $bannerCopy = match ($server->status) {
        \App\Enums\ServerState::InstallFailed => [
            'title' => 'Install failed',
            'subtitle' => 'Setup did not complete. Reinstall the server from Settings.',
        ],
        \App\Enums\ServerState::ReinstallFailed => [
            'title' => 'Reinstall failed',
            'subtitle' => 'The reinstall did not finish. Try again from Settings.',
        ],
        default => [
            'title' => 'Setting up your server',
            'subtitle' => 'Installing ' . $eggName,
        ],
    };
    $showProgress = $server->status === \App\Enums\ServerState::Installing;
    $cards = [
        ['label' => 'Egg', 'value' => $eggName],
        ['label' => 'Version', 'value' => $server->version],
    ];
@endphp

<x-overview.state-banner
    :variant="$bannerVariant"
    :title="$bannerCopy['title']"
    :subtitle="$bannerCopy['subtitle']"
    :icon="$showProgress ? 'tabler-tool' : 'tabler-alert-triangle'"
    :show-progress="$showProgress"
/>

<div class="overview-stat-grid overview-stat-grid--3 grid grid-cols-1 md:grid-cols-3 gap-3 overview-stat-grid--muted">
    @foreach ($cards as $card)
        <div class="overview-stat-card @if ($card['value'] === null) overview-stat-card--muted @endif">
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
