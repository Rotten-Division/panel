@props([
    'variant' => 'default',
    'title' => '',
    'subtitle' => null,
    'showProgress' => false,
    'icon' => null,
])

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
    @if ($showProgress)
        <div class="overview-banner__progress">
            <div class="overview-banner__progress-bar"></div>
        </div>
    @endif
</div>
