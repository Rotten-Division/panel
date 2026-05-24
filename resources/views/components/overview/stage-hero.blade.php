@props(['variant' => 'stashed'])

<section class="overview-stage-hero overview-stage-hero--{{ $variant }}">
    <div class="overview-stage-hero__prose">
        <h2 class="overview-stage-hero__title">{{ $title ?? '' }}</h2>
        <p class="overview-stage-hero__body">{{ $body ?? '' }}</p>
        @isset($cta)
            <div class="overview-stage-hero__cta">{{ $cta }}</div>
        @endisset
    </div>
    @isset($illustration)
        <div class="overview-stage-hero__illustration">{{ $illustration }}</div>
    @endisset
    {{ $slot }}
</section>
