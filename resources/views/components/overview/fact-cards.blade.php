@props(['cards' => []])

<div class="overview-fact-cards grid grid-cols-3 gap-3">
    @foreach ($cards as $card)
        <div class="overview-fact-card overview-fact-card--{{ $card['variant'] ?? 'default' }}">
            <p class="overview-fact-card__label">
                @if (! empty($card['icon']))
                    <x-filament::icon :icon="$card['icon']" class="overview-fact-card__icon size-3" />
                @endif
                {{ $card['label'] }}
            </p>
            <p class="overview-fact-card__value">{{ $card['value'] }}</p>
        </div>
    @endforeach
</div>
