@php
    /** @var array{label: string, unit?: string, current: string, allocation?: ?string, progress?: ?array{value: float, max: float, colour: string}, ticks: array<int, string>, series: array<int, array{0: float, 1: float}>, series2?: ?array<int, array{0: float, 1: float}>, legend?: ?array{in: array{value: string, unit: string}, out: array{value: string, unit: string}}} $card */
    // the chart widgets listen for `refresh-overview` dispatched from the
    // page's refreshLiveData poll, so the card template itself doesn't poll.
    $width = 320;
    $chartHeight = 80;
    $chartTop = 8;
    $chartBottom = $chartTop + $chartHeight;
    $pathFor = static function (array $samples) use ($width, $chartTop, $chartBottom): string {
        if (empty($samples)) {
            return '';
        }
        $segments = [];
        foreach ($samples as $i => [$x, $y]) {
            $segments[] = ($i === 0 ? 'M' : 'L').number_format($x, 2, '.', '').','.number_format($y, 2, '.', '');
        }

        return implode(' ', $segments);
    };
    // closed area path for the gradient fill — same coords as the stroke
    // path, then drop to chartBottom on the last x and return to chartBottom
    // on the first x before closing.
    $areaFor = static function (array $samples) use ($pathFor, $chartBottom): string {
        if (empty($samples)) {
            return '';
        }
        $stroke = $pathFor($samples);
        $firstX = number_format((float) $samples[0][0], 2, '.', '');
        $lastX = number_format((float) end($samples)[0], 2, '.', '');
        $floor = number_format((float) $chartBottom, 2, '.', '');

        return $stroke." L{$lastX},{$floor} L{$firstX},{$floor} Z";
    };
    // unique gradient ids per render so multiple cards on the same page
    // don't share defs and the right gradient picks for each card.
    $gradId = 'overview-area-'.bin2hex(random_bytes(4));
    // y-axis tick positions as a 0..100 percentage of the chart height.
    // labels are rendered as positioned HTML outside the SVG so they don't
    // inherit the SVG's non-uniform horizontal stretch.
    $tickPositions = [
        ['percent' => 0, 'label' => $card['ticks'][0] ?? ''],
        ['percent' => 50, 'label' => $card['ticks'][1] ?? ''],
        ['percent' => 100, 'label' => $card['ticks'][2] ?? ''],
    ];
@endphp

<div class="overview-resource-card">
    <div class="overview-resource-card__stat">
        <div class="overview-resource-card__row">
            <p class="overview-resource-card__label">{{ $card['label'] }}</p>
            @if (! empty($card['legend']))
                <div class="overview-resource-card__legend">
                    <span class="overview-resource-card__legend-item">
                        <span class="overview-resource-card__legend-swatch overview-resource-card__legend-swatch--in" aria-hidden="true"></span>
                        ↓ {{ $card['legend']['in']['value'] }} {{ $card['legend']['in']['unit'] }} in
                    </span>
                    <span class="overview-resource-card__legend-separator" aria-hidden="true">·</span>
                    <span class="overview-resource-card__legend-item">
                        <span class="overview-resource-card__legend-swatch overview-resource-card__legend-swatch--out" aria-hidden="true"></span>
                        ↑ {{ $card['legend']['out']['value'] }} {{ $card['legend']['out']['unit'] }} out
                    </span>
                </div>
            @endif
        </div>

        @if (! empty($card['current']))
            <div class="overview-resource-card__row">
                <p class="overview-resource-card__value">{{ $card['current'] }}</p>
                @if (! empty($card['allocation']))
                    <p class="overview-resource-card__allocation">{{ $card['allocation'] }}</p>
                @endif
            </div>
        @endif

        @if (! empty($card['progress']))
            @php
                $progressPercent = $card['progress']['max'] > 0 ? min(100, max(0, ($card['progress']['value'] / $card['progress']['max']) * 100)) : 0;
            @endphp
            <div class="overview-resource-card__bar">
                <div class="overview-resource-card__bar-fill" style="width: {{ number_format($progressPercent, 1, '.', '') }}%; background: var(--{{ $card['progress']['colour'] }});"></div>
            </div>
        @endif
    </div>

    <div class="overview-resource-card__chart">
        <div class="overview-resource-card__plot">
            <svg viewBox="0 0 {{ $width }} {{ $chartBottom + 16 }}" preserveAspectRatio="none" class="overview-resource-card__svg" aria-hidden="true">
                <defs>
                    <linearGradient id="{{ $gradId }}-in" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--hearth)" stop-opacity="0.38" />
                        <stop offset="70%" stop-color="var(--hearth)" stop-opacity="0.14" />
                        <stop offset="100%" stop-color="var(--hearth)" stop-opacity="0" />
                    </linearGradient>
                    @if (! empty($card['series2']))
                        <linearGradient id="{{ $gradId }}-out" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--azure)" stop-opacity="0.30" />
                            <stop offset="70%" stop-color="var(--azure)" stop-opacity="0.10" />
                            <stop offset="100%" stop-color="var(--azure)" stop-opacity="0" />
                        </linearGradient>
                    @endif
                </defs>

                @foreach ($tickPositions as $tick)
                    @php($yLine = $chartTop + ($chartHeight * $tick['percent'] / 100))
                    <line x1="0" x2="{{ $width }}" y1="{{ $yLine }}" y2="{{ $yLine }}" stroke="var(--graphite)" stroke-width="0.5" stroke-dasharray="2 3" />
                @endforeach

                @if (! empty($card['series2']))
                    <path d="{{ $areaFor($card['series2']) }}" fill="url(#{{ $gradId }}-out)" stroke="none" />
                @endif
                <path d="{{ $areaFor($card['series']) }}" fill="url(#{{ $gradId }}-in)" stroke="none" />

                <path d="{{ $pathFor($card['series']) }}" fill="none" stroke="var(--hearth)" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />

                @if (! empty($card['series2']))
                    <path d="{{ $pathFor($card['series2']) }}" fill="none" stroke="var(--azure)" stroke-width="1.5" stroke-dasharray="4 2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
                @endif
            </svg>
        </div>

        {{-- ticks are a flex column on the right of the chart, the column
             auto-sizes to its widest label so CPU "200%" and memory "3.0 GiB"
             each reserve only the gutter they actually need. --}}
        <div class="overview-resource-card__ticks" aria-hidden="true">
            @foreach ($tickPositions as $tick)
                <span class="overview-resource-card__tick">{{ $tick['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>
