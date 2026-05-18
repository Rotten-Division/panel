@php
    /** @var array{step: string, bytes: int, total_bytes: int}|null $payload */
    /** @var ?string $source */
    /** @var ?string $destination */
    /** @var ?string $stepCopy */
    /** @var string $bytesCopy */
    /** @var string $totalCopy */
    $step = $payload['step'] ?? null;
    $bytes = $payload['bytes'] ?? 0;
    $total = $payload['total_bytes'] ?? 0;
    $pct = $total > 0 ? min(100, ($bytes / $total) * 100) : null;
@endphp

<div class="overview-transfer-detail">
    <div class="overview-transfer-detail__route">
        <div class="overview-transfer-detail__node">
            <p class="overview-transfer-detail__label">From</p>
            <p class="overview-transfer-detail__node-name">{{ $source ?? '—' }}</p>
        </div>
        <div class="overview-transfer-detail__arrow" aria-hidden="true">
            <x-filament::icon icon="tabler-arrow-right" class="size-4" />
        </div>
        <div class="overview-transfer-detail__node overview-transfer-detail__node--target">
            <p class="overview-transfer-detail__label">To</p>
            <p class="overview-transfer-detail__node-name">{{ $destination ?? '—' }}</p>
        </div>
    </div>

    <div class="overview-transfer-detail__step">
        <p class="overview-transfer-detail__label">Current step</p>
        <p class="overview-transfer-detail__step-name">
            @if ($stepCopy)
                {{ $stepCopy }}
            @else
                Waiting on wings…
            @endif
        </p>
    </div>

    @if ($step === 'uploading' && $total > 0)
        <div class="overview-transfer-detail__bytes">
            <p class="overview-transfer-detail__label">Progress</p>
            <p class="overview-transfer-detail__bytes-value">
                {{ $bytesCopy }} <span class="overview-transfer-detail__bytes-sep">/</span> {{ $totalCopy }}
            </p>
            <div class="overview-bar overview-bar--success">
                <div class="overview-bar__fill" style="width: {{ number_format($pct ?? 0, 1, '.', '') }}%"></div>
            </div>
        </div>
    @elseif ($step !== null)
        <div class="overview-transfer-detail__bytes">
            <p class="overview-transfer-detail__label">Progress</p>
            <p class="overview-transfer-detail__bytes-value overview-stat-card__placeholder">Indeterminate</p>
        </div>
    @endif
</div>
