@props(['done' => false, 'active' => false, 'label' => '', 'detail' => null, 'percent' => null])

<div @class([
    'overview-step',
    'overview-step--done' => $done,
    'overview-step--active' => $active,
])>
    <div class="overview-step__row">
        <span class="overview-step__dot">
            @if ($done)
                <x-filament::icon icon="tabler-check" class="size-3" />
            @elseif ($active)
                <x-filament::icon icon="tabler-loader-2" class="size-3 overview-step__spinner" />
            @else
                <x-filament::icon icon="tabler-point" class="size-3" />
            @endif
        </span>
        <span class="overview-step__label">{{ $label }}</span>
        @if ($detail)
            <span class="overview-step__detail">{{ $detail }}</span>
        @endif
    </div>
    @if ($percent !== null)
        <div class="overview-step__progress">
            <x-overview.progress-band variant="stashed" :percent="$percent" />
        </div>
    @endif
</div>
