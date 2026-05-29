@props(['variant' => 'default', 'percent' => null])

{{-- indeterminate shuttle by default; a determinate fill when a percent is
     given (the streaming leg). do NOT nest inside state-banner, the canvas
     places this as a separate strip. --}}
@php($determinate = $percent !== null)
<div @class([
        'overview-progress-band',
        "overview-progress-band--{$variant}",
        'overview-progress-band--determinate' => $determinate,
    ]) aria-hidden="true">
    <i @if($determinate) style="width: {{ $percent }}%" @endif></i>
</div>
