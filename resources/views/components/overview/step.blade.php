@props(['done' => false, 'active' => false, 'label' => '', 'detail' => null, 'percent' => null])

@php($tracking = $active && ! is_null($percent))
<div {{ $attributes }} @class([
    'overview-step',
    'overview-step--done' => $done,
    'overview-step--active' => $active,
])>
    <div class="overview-step__row">
        <span class="overview-step__dot">
            @if ($done)
                <x-filament::icon icon="tabler-check" class="size-3" />
            @elseif ($tracking)
                {{-- pathLength normalises the circle to 100 so the offset is
                     just 100 - percent. --}}
                <svg class="overview-step__ring size-3" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="overview-step__ring-track" cx="12" cy="12" r="9" stroke-width="2" />
                    <circle class="overview-step__ring-fill" cx="12" cy="12" r="9" stroke-width="2"
                        pathLength="100" stroke-dasharray="100" style="stroke-dashoffset: {{ 100 - (float) $percent }}" />
                </svg>
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
</div>
