@props([
    'variant' => 'default',
    'title' => '',
    'subtitle' => null,
    'icon' => null,
])

{{-- progress band is now a separate <x-overview.progress-band /> sibling
     element, see design canvas which renders the shuttle as a strip below
     the banner, not inside it. --}}
<div class="overview-banner overview-banner--{{ $variant }}">
    <div class="overview-banner__accent"></div>
    <div class="overview-banner__body">
        @if ($icon)
            <x-filament::icon :icon="$icon" class="size-5 overview-banner__icon" />
        @endif
        <div class="overview-banner__content">
            <p class="overview-banner__title">{{ $title }}</p>
            @if ($subtitle)
                <p class="overview-banner__subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        {{ $slot }}
    </div>
</div>
