@props(['variant' => 'stashed', 'mirror' => false])

<section class="overview-stage-hero overview-stage-hero--{{ $variant }}">
    <div class="overview-stage-hero__inner">
        @isset($illustration)
            <div @class([
                'overview-stage-hero__illustration',
                'overview-stage-hero__illustration--mirror' => $mirror,
            ])>{{ $illustration }}</div>
        @endisset

        <div class="overview-stage-hero__prose">
            <h2 class="overview-stage-hero__title">{{ $title ?? '' }}</h2>
            @isset($body)
                <p class="overview-stage-hero__body">{{ $body }}</p>
            @endisset
        </div>

        @isset($progress)
            <div class="overview-stage-hero__progress">{{ $progress }}</div>
        @endisset

        @isset($cta)
            <div class="overview-stage-hero__cta">{{ $cta }}</div>
        @endisset
    </div>
</section>
