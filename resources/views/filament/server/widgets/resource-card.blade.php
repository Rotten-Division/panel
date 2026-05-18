@php
    /** @var array{label: string, unit?: string, current: string, allocation?: ?string, progress?: ?array{value: float, max: float, colour: string}, series: array<int, array{0: float, 1: float}>, series2?: ?array<int, array{0: float, 1: float}>, labels?: array<int, string>, labels2?: array<int, string>, times?: array<int, string>, legend?: ?array{in: array{value: string, unit: string}, out: array{value: string, unit: string}}} $card */
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
    // dashed horizontal gridline positions at 0 / 50 / 100% of the
    // chart height. y-axis labels were dropped — the hover tooltip
    // surfaces precise values so the labels added noise without value.
    $gridlinePercents = [0, 50, 100];
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

    @php
        // last in-series point in viewbox coords → percent of plot wrapper
        // so the live dot anchors at exactly the rightmost line endpoint
        // regardless of how the svg stretches. viewbox is W × ($chartBottom+8);
        // the +8 is bottom padding inside the viewbox (symmetric with the
        // 8px top, so the chart line sits in the visual centre of the
        // plot area instead of being pushed toward the top), so bottom%
        // must use the full viewbox height as the divisor (NOT just
        // $chartHeight).
        $viewBoxH = $chartBottom + 4;
        $lastPoint = ! empty($card['series']) ? end($card['series']) : null;
        $dotInLeft = $lastPoint !== null ? ($lastPoint[0] / $width) * 100 : null;
        $dotInBottom = $lastPoint !== null ? (($viewBoxH - $lastPoint[1]) / $viewBoxH) * 100 : null;

        $lastPoint2 = ! empty($card['series2'] ?? null) ? end($card['series2']) : null;
        $dotOutLeft = $lastPoint2 !== null ? ($lastPoint2[0] / $width) * 100 : null;
        $dotOutBottom = $lastPoint2 !== null ? (($viewBoxH - $lastPoint2[1]) / $viewBoxH) * 100 : null;
    @endphp

    <div class="overview-resource-card__chart-zone">
        <div
            class="overview-resource-card__plot"
            data-pts="{{ json_encode($card['series'] ?? []) }}"
            data-pts2="{{ json_encode($card['series2'] ?? []) }}"
            data-labels="{{ json_encode($card['labels'] ?? []) }}"
            data-labels2="{{ json_encode($card['labels2'] ?? []) }}"
            data-times="{{ json_encode($card['times'] ?? []) }}"
            data-vb-w="{{ $width }}"
            data-vb-h="{{ $viewBoxH }}"
        >
            <svg viewBox="0 0 {{ $width }} {{ $viewBoxH }}" preserveAspectRatio="none" class="overview-resource-card__svg" aria-hidden="true">
                <defs>
                    <linearGradient id="{{ $gradId }}-in" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="var(--hearth)" stop-opacity="0.38" />
                        <stop offset="100%" stop-color="var(--hearth)" stop-opacity="0.10" />
                    </linearGradient>
                    @if (! empty($card['series2']))
                        <linearGradient id="{{ $gradId }}-out" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="var(--azure)" stop-opacity="0.30" />
                            <stop offset="100%" stop-color="var(--azure)" stop-opacity="0.08" />
                        </linearGradient>
                    @endif
                </defs>

                @foreach ($gridlinePercents as $percent)
                    @php($yLine = $chartTop + ($chartHeight * $percent / 100))
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

            @if ($dotInLeft !== null)
                <span
                    class="overview-resource-card__dot overview-resource-card__dot--in"
                    style="left: {{ number_format($dotInLeft, 2, '.', '') }}%; bottom: {{ number_format($dotInBottom, 2, '.', '') }}%;"
                    aria-hidden="true"
                ></span>
            @endif

            @if ($dotOutLeft !== null)
                <span
                    class="overview-resource-card__dot overview-resource-card__dot--out"
                    style="left: {{ number_format($dotOutLeft, 2, '.', '') }}%; bottom: {{ number_format($dotOutBottom, 2, '.', '') }}%;"
                    aria-hidden="true"
                ></span>
            @endif

            {{-- hover overlay: mutated by the script at the bottom of this
                 view in response to mousemove on the plot. kept as static
                 html so livewire morphs don't fight us — style mutations
                 happen at runtime only. --}}
            <div class="overview-resource-card__hover" data-overlay aria-hidden="true" style="display: none;">
                <span class="overview-resource-card__hover-rail" data-rail></span>
                <span class="overview-resource-card__hover-dot overview-resource-card__hover-dot--in" data-dot-in></span>
                <span class="overview-resource-card__hover-dot overview-resource-card__hover-dot--out" data-dot-out style="display: none;"></span>
                <span class="overview-resource-card__tooltip" data-tooltip>
                    <span data-label></span>
                    <span class="overview-resource-card__tooltip-row" data-label2 style="display: none;"></span>
                    <span class="overview-resource-card__tooltip-time" data-time style="display: none;"></span>
                </span>
            </div>
        </div>

        {{-- x-axis timestamp row removed — the chart's sub-minute scale
             makes per-tick HH:MM:SS labels noisier than informative. the
             per-sample timestamp lives on the hover tooltip instead. --}}
    </div>
</div>

@script
<script>
    // delegated mousemove handler: walks up to find a chart plot, reads
    // the JSON data-* attributes, finds the nearest sample by x distance,
    // and mutates the overlay siblings directly. survives livewire morphs
    // since the listener is on document and re-reads the dataset on every
    // move (snap-to-nearest each poll). the IIFE guard means rendering
    // three widgets only binds the listener once.
    (() => {
        if (window.__ospOverviewHoverBound) { return; }
        window.__ospOverviewHoverBound = true;

        const findPlot = (target) =>
            target?.closest?.('.overview-resource-card__plot') ?? null;

        const hidePlotOverlay = (plot) => {
            const overlay = plot.querySelector('[data-overlay]');
            if (overlay) { overlay.style.display = 'none'; }
        };

        const updatePlot = (plot, event) => {
            const overlay = plot.querySelector('[data-overlay]');
            if (!overlay) { return; }

            let pts = [];
            let pts2 = [];
            let labels = [];
            let labels2 = [];
            let times = [];
            try { pts = JSON.parse(plot.dataset.pts || '[]'); } catch (_) {}
            try { pts2 = JSON.parse(plot.dataset.pts2 || '[]'); } catch (_) {}
            try { labels = JSON.parse(plot.dataset.labels || '[]'); } catch (_) {}
            try { labels2 = JSON.parse(plot.dataset.labels2 || '[]'); } catch (_) {}
            try { times = JSON.parse(plot.dataset.times || '[]'); } catch (_) {}

            if (!pts.length) { overlay.style.display = 'none'; return; }

            const vbW = parseFloat(plot.dataset.vbW) || 320;
            const vbH = parseFloat(plot.dataset.vbH) || 104;

            const rect = plot.getBoundingClientRect();
            const mx = event.clientX - rect.left;
            const vbX = (mx / rect.width) * vbW;

            let nearest = 0;
            let minDist = Infinity;
            for (let i = 0; i < pts.length; i++) {
                const d = Math.abs(pts[i][0] - vbX);
                if (d < minDist) { minDist = d; nearest = i; }
            }

            const pt = pts[nearest];
            const left = (pt[0] / vbW) * 100;
            const bottom = ((vbH - pt[1]) / vbH) * 100;

            overlay.style.display = '';

            const rail = overlay.querySelector('[data-rail]');
            if (rail) { rail.style.left = left + '%'; }

            const dotIn = overlay.querySelector('[data-dot-in]');
            if (dotIn) {
                dotIn.style.left = left + '%';
                dotIn.style.bottom = bottom + '%';
            }

            const dotOut = overlay.querySelector('[data-dot-out]');
            const pt2 = pts2[nearest];
            if (dotOut) {
                if (pt2) {
                    dotOut.style.display = '';
                    dotOut.style.left = left + '%';
                    dotOut.style.bottom = ((vbH - pt2[1]) / vbH) * 100 + '%';
                } else {
                    dotOut.style.display = 'none';
                }
            }

            const tooltip = overlay.querySelector('[data-tooltip]');
            if (tooltip) {
                tooltip.style.left = left + '%';
                tooltip.classList.toggle('overview-resource-card__tooltip--flip', left > 70);
            }

            const labelEl = overlay.querySelector('[data-label]');
            if (labelEl) { labelEl.textContent = labels[nearest] ?? ''; }

            const label2El = overlay.querySelector('[data-label2]');
            if (label2El) {
                const v = labels2[nearest];
                if (v) {
                    label2El.style.display = '';
                    label2El.textContent = v;
                } else {
                    label2El.style.display = 'none';
                }
            }

            const timeEl = overlay.querySelector('[data-time]');
            if (timeEl) {
                const t = times[nearest];
                if (t) {
                    timeEl.style.display = '';
                    timeEl.textContent = t;
                } else {
                    timeEl.style.display = 'none';
                }
            }
        };

        document.addEventListener('mousemove', (event) => {
            const plot = findPlot(event.target);
            if (plot) { updatePlot(plot, event); }
        }, true);

        // mouseleave doesn't bubble, but capture-phase listeners on
        // document still receive it when the target is a descendant —
        // so this one handler covers exits from any of the three plots.
        document.addEventListener('mouseleave', (event) => {
            const plot = findPlot(event.target);
            if (plot) { hidePlotOverlay(plot); }
        }, true);
    })();
</script>
@endscript
