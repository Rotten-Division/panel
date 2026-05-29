@props(['title' => null, 'sub' => null, 'steps' => [], 'progress' => false])

<div class="overview-step-card">
    @if ($title || $sub)
        <div class="overview-step-card__header">
            @if ($title)<p class="overview-step-card__title">{{ $title }}</p>@endif
            @if ($sub)<p class="overview-step-card__sub">{{ $sub }}</p>@endif
        </div>
    @endif
    @if ($progress !== false)
        <div class="overview-step-card__progress">
            <x-overview.progress-band variant="stashed" :percent="$progress" />
        </div>
    @endif
    <div class="overview-step-card__list">
        @foreach ($steps as $step)
            <x-overview.step
                :done="$step['done'] ?? false"
                :active="$step['active'] ?? false"
                :label="$step['label']"
                :detail="$step['detail'] ?? null"
            />
        @endforeach
    </div>
</div>
