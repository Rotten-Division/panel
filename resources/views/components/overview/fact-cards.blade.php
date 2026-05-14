@props(['cards' => []])

<div class="overview-fact-cards grid grid-cols-3 gap-3">
    @foreach ($cards as $card)
        <div class="overview-fact-card overview-fact-card--{{ $card['variant'] ?? 'default' }}">
            @if (! empty($card['icon']))
                <div class="overview-fact-card__icon">
                    <x-filament::icon :icon="$card['icon']" class="size-3.5" />
                </div>
            @endif
            <div class="overview-fact-card__content">
                <p class="overview-fact-card__label">{{ $card['label'] }}</p>
                <p class="overview-fact-card__value">{{ $card['value'] }}</p>
            </div>
        </div>
    @endforeach
</div>
