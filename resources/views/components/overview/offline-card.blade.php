@props(['label' => 'Server Offline'])

<div {{ $attributes->merge(['class' => 'overview-offline-card']) }}>
    <div class="overview-offline-card__content">
        {{ $slot }}
    </div>
    <div class="overview-offline-card__overlay">
        <span class="overview-offline-card__chip">{{ $label }}</span>
    </div>
</div>
