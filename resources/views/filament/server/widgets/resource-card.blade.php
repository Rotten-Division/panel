@php
    /** @var array{label: string, unit?: string, current: string, allocation?: ?string, progress?: ?array{value: float, max: float, colour: string}, ticks: array<int, string>, series: array<int, array{0: float, 1: float}>, series2?: ?array<int, array{0: float, 1: float}>, legend?: ?array{in: array{value: string, unit: string}, out: array{value: string, unit: string}}} $card */
    /** the include path uses $poll=false so the static preview doesn't try to call refreshSeries on the admin page livewire component. */
    $poll = $poll ?? true;
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
    // y-axis tick positions as a 0..100 percentage of the chart height.
    // labels are rendered as positioned HTML outside the SVG so they don't
    // inherit the SVG's non-uniform horizontal stretch.
    $tickPositions = [
        ['percent' => 0, 'label' => $card['ticks'][0] ?? ''],
        ['percent' => 50, 'label' => $card['ticks'][1] ?? ''],
        ['percent' => 100, 'label' => $card['ticks'][2] ?? ''],
    ];
@endphp

<div @if ($poll) wire:poll.1s="refreshSeries" @endif class="overview-resource-card">
    <div class="overview-resource-card__stat">
        <div class="overview-resource-card__row">
            <p class="overview-resource-card__label">{{ $card['label'] }}</p>
            @if (! empty($card['legend']))
                <div class="overview-resource-card__legend">
                    <span>↓ {{ $card['legend']['in']['value'] }} {{ $card['legend']['in']['unit'] }} in</span>
                    <span class="overview-resource-card__legend-separator">·</span>
                    <span>↑ {{ $card['legend']['out']['value'] }} {{ $card['legend']['out']['unit'] }} out</span>
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
        <svg viewBox="0 0 {{ $width }} {{ $chartBottom + 16 }}" preserveAspectRatio="none" class="overview-resource-card__svg" aria-hidden="true">
            @foreach ($tickPositions as $tick)
                @php($yLine = $chartTop + ($chartHeight * $tick['percent'] / 100))
                <line x1="0" x2="{{ $width }}" y1="{{ $yLine }}" y2="{{ $yLine }}" stroke="var(--graphite)" stroke-width="0.5" stroke-dasharray="2 3" />
            @endforeach

            <path d="{{ $pathFor($card['series']) }}" fill="none" stroke="var(--hearth)" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />

            @if (! empty($card['series2']))
                <path d="{{ $pathFor($card['series2']) }}" fill="none" stroke="#6FA8E0" stroke-width="1.5" stroke-dasharray="4 2" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round" />
            @endif
        </svg>

        <div class="overview-resource-card__ticks" aria-hidden="true">
            @foreach ($tickPositions as $tick)
                <span class="overview-resource-card__tick" style="top: calc({{ $chartTop }}px + ({{ $chartHeight }}px * {{ $tick['percent'] }} / 100));">{{ $tick['label'] }}</span>
            @endforeach
        </div>
    </div>
</div>
