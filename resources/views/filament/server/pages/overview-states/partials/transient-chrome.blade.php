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
