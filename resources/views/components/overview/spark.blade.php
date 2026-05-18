@props([
    'title',
    'value' => null,
    'series' => [],
    'color' => 'hearth',
    'muted' => false,
    'height' => 70,
])

@php
    $width = 240;
    $h = $height;
    $count = count($series);

    if ($count === 0) {
        $linePath = '';
        $areaPath = '';
    } else {
        if ($count === 1) {
            // single point can't draw a line — duplicate so it renders as
            // a flat segment at that value.
            $series = [$series[0], $series[0]];
            $count = 2;
        }

        $stepX = $width / ($count - 1);
        $pts = [];
        foreach (array_values($series) as $i => $v) {
            $pts[] = [$i * $stepX, $h - 8 - $v * ($h - 16)];
        }

        $linePath = '';
        foreach ($pts as $i => [$x, $y]) {
            $linePath .= ($i === 0 ? 'M' : 'L').number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
        }

        $areaPath = $linePath." L{$width},{$h} L0,{$h} Z";
    }
@endphp

<div @class([
    'overview-spark',
    "overview-spark--{$color}",
    'overview-spark--muted' => $muted,
])>
    <div class="overview-spark__title">
        <span>{{ $title }}</span>
        @if ($value !== null)
            <b class="font-mono">{{ $value }}</b>
        @endif
    </div>
    <svg viewBox="0 0 {{ $width }} {{ $h }}" preserveAspectRatio="none" class="overview-spark__svg" aria-hidden="true">
        @if ($areaPath)
            <path class="overview-spark__fill" d="{{ $areaPath }}" fill="currentColor" fill-opacity="0.10" />
            <path class="overview-spark__line" d="{{ $linePath }}" stroke="currentColor" stroke-width="1.25" fill="none" vector-effect="non-scaling-stroke" />
        @endif
    </svg>
</div>
