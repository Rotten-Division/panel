@props(['variant' => 'default'])

{{-- indeterminate progress shuttle. matches design canvas .fs-progress
     (overview.css:398-419). standalone sibling under the banner, do
     NOT nest inside state-banner; the canvas places this as a
     separate strip and the banner stays compact. --}}
<div class="overview-progress-band overview-progress-band--{{ $variant }}" aria-hidden="true">
    <i></i>
</div>
