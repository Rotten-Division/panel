@props(['title' => null, 'sub' => null, 'note' => null])

<div class="overview-reason-card">
    @if ($title || $sub)
        <div class="overview-reason-card__header">
            @if ($title)<p class="overview-reason-card__title">{{ $title }}</p>@endif
            @if ($sub)<p class="overview-reason-card__sub">{{ $sub }}</p>@endif
        </div>
    @endif
    <div class="overview-reason-card__body">
        {{ $slot }}
        @if ($note)
            <p class="overview-reason-card__note">{{ $note }}</p>
        @endif
    </div>
</div>
