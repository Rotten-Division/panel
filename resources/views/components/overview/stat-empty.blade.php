@props(['caption' => null])

<p class="overview-stat-card__value overview-stat-card__value--empty">
    <span class="overview-stat-empty-bar" aria-hidden="true"></span>
    @if ($caption)
        <span class="overview-stat-empty-caption">{{ $caption }}</span>
    @endif
</p>
