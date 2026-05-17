@php
    /** @var \App\Models\Server $server */
    $diskUsed = $this->diskUsedBytes;
    $diskLimit = (int) ($server->disk ?? 0) * 1024 * 1024;
    $worldName = $server->name;

    $bannerVariant = match ($server->status) {
        \App\Enums\ServerState::InstallFailed, \App\Enums\ServerState::ReinstallFailed => 'suspended',
        default => 'installing',
    };

    $bannerKey = match ($server->status) {
        \App\Enums\ServerState::InstallFailed => 'install_failed',
        \App\Enums\ServerState::ReinstallFailed => 'reinstall_failed',
        default => 'installing',
    };
    $bannerCopy = trans("server/overview.installing.$bannerKey");

    $showProgress = $server->status === \App\Enums\ServerState::Installing;
@endphp

<x-overview.state-banner
    :variant="$bannerVariant"
    :title="$bannerCopy['title']"
    :subtitle="$bannerCopy['subtitle']"
    :icon="$showProgress ? 'tabler-download' : 'tabler-alert-triangle'"
/>

@if ($showProgress)
    <x-overview.progress-band variant="honey" />
@endif

<div wire:poll.1s="refreshLiveData" class="overview-stat-grid grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 overview-stat-grid--muted">
    <div class="overview-stat-card">
        <p class="overview-stat-card__label">Egg</p>
        <p class="overview-stat-card__value">{{ $server->egg?->name ?? '—' }}</p>
    </div>

    <div class="overview-stat-card">
        <p class="overview-stat-card__label">Version</p>
        <p class="overview-stat-card__value">
            @if ($server->version)
                {{ $server->version }}
            @else
                <span class="overview-stat-card__placeholder">—</span>
            @endif
        </p>
    </div>

    <div class="overview-stat-card">
        <p class="overview-stat-card__label">World</p>
        <p class="overview-stat-card__value font-mono">{{ $worldName }}</p>
    </div>

    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">CPU load</p>
        <p class="overview-stat-card__value"><span class="overview-stat-card__placeholder">—</span></p>
    </div>

    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">Memory</p>
        <p class="overview-stat-card__value"><span class="overview-stat-card__placeholder">—</span></p>
    </div>

    <div class="overview-stat-card overview-stat-card--muted">
        <p class="overview-stat-card__label">Disk</p>
        <p class="overview-stat-card__value">
            {{ number_format($diskUsed / 1024 / 1024 / 1024, 2) }} GiB
            <span class="overview-stat-card__sub-inline">/ {{ $diskLimit > 0 ? number_format($diskLimit / 1024 / 1024 / 1024, 0).' GiB' : '∞' }}</span>
        </p>
    </div>
</div>

<x-filament-widgets::widgets
    :columns="1"
    :data="$this->getWidgetData()"
    :widgets="[\App\Filament\Server\Widgets\ServerConsole::class]"
/>
