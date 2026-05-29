@props(['title' => null, 'sub' => null, 'steps' => []])

<div class="overview-step-card">
    @if ($title || $sub)
        <div class="overview-step-card__header">
            @if ($title)<p class="overview-step-card__title">{{ $title }}</p>@endif
            @if ($sub)<p class="overview-step-card__sub">{{ $sub }}</p>@endif
        </div>
    @endif
    <div class="overview-step-card__list">
        @foreach ($steps as $step)
            <x-overview.step
                :done="$step['done'] ?? false"
                :active="$step['active'] ?? false"
                :label="$step['label']"
                :detail="$step['detail'] ?? null"
                :percent="$step['percent'] ?? null"
            />
        @endforeach
    </div>
</div>
