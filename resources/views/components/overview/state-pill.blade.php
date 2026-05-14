@php
    /** @var \App\View\Components\Overview\StatePill $component */
    $variant = $component->variant();
    $label = $component->label();
    $pulses = $component->pulses();
@endphp

<span class="overview-state-pill overview-state-pill--{{ $variant }}">
    <span class="overview-state-pill__dot @if ($pulses) overview-state-pill__dot--pulse @endif"></span>
    <span class="overview-state-pill__label">{{ $label }}</span>
</span>
