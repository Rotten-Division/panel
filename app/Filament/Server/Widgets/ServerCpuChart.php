<?php

namespace App\Filament\Server\Widgets;

use App\Enums\CustomizationKey;
use App\Models\Server;
use App\Support\ResourceCard;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class ServerCpuChart extends Widget
{
    protected string $view = 'filament.server.widgets.resource-card';

    public ?Server $server = null;

    /** @var array<int, float> */
    public array $series = [];

    /** per-sample HH:MM:SS strings in the user's timezone, one entry per
     *  visible cache sample. populates the hover tooltip's time row and
     *  drives the three x-axis ticks via pickAxisTicks(). */
    /** @var array<int, string> */
    public array $times = [];

    /** three evenly spaced timestamp ticks (oldest, middle, newest)
     *  rendered on the chart's x-axis. falls back to em-dashes when the
     *  cache is empty. */
    /** @var array{0: string, 1: string, 2: string} */
    public array $axisTicks = ['—', '—', '—'];

    /** when true the mount snapshot is the final render — the
     *  refresh-overview event is ignored. used by stopped/stopping
     *  states to pin the chart at the last running value instead of
     *  re-polling against an idle cache. */
    public bool $frozen = false;

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        // visible across running, starting, stopped, stopping. the chart
        // renders empty samples gracefully when the container is offline.
        // conflict states (nest, installing, transfer, suspended) still
        // hide because the server has nothing to graph against.
        return !$server->isInConflictState();
    }

    public function mount(): void
    {
        // always pull the initial snapshot, even when frozen — the freeze
        // gate only applies to subsequent poll-driven refreshes.
        $this->pullSeries();
    }

    #[On('refresh-overview')]
    public function refreshSeries(): void
    {
        if ($this->frozen) {
            return;
        }

        $this->pullSeries();
    }

    private function pullSeries(): void
    {
        $period = (int) (user()?->getCustomization(CustomizationKey::ConsoleGraphPeriod) ?? 30);
        $tz = user()?->timezone ?? config('app.timezone', 'UTC');
        $raw = cache()->get("servers.{$this->server?->id}.cpu_absolute") ?? [];
        $this->series = collect($raw)
            ->slice(-$period)
            ->map(fn ($value) => (float) round($value, 2))
            ->values()
            ->all();
        $this->times = ResourceCard::formatSampleTimes($raw, $period, $tz);
        $this->axisTicks = ResourceCard::pickAxisTicks($this->times);
    }

    public function getCurrentValue(): float
    {
        return empty($this->series) ? 0.0 : (float) end($this->series);
    }

    public function getMaxValue(): float
    {
        // server-side cpu cap, expressed as percent of one core (200 = 2 cores).
        // when unlimited (0), defer to the node total. callers fall back to
        // current value when both are zero so the progress bar still renders.
        $cap = (float) ($this->server?->cpu ?? 0);
        if ($cap > 0) {
            return $cap;
        }

        return max($this->getCurrentValue(), 1.0);
    }

    /** @return array<int, float> */
    public function getYAxisTicks(): array
    {
        return ResourceCard::ticks($this->series);
    }

    public function getProgressColor(): string
    {
        $max = $this->getMaxValue();
        $pct = $max > 0 ? ($this->getCurrentValue() / $max) * 100 : 0;

        return ResourceCard::progressColour($pct);
    }

    protected function getViewData(): array
    {
        $ticks = $this->getYAxisTicks();

        // points() duplicates a single sample so the line renders flat;
        // mirror that here so labels stays index-aligned with series.
        $raw = $this->series;
        if (count($raw) === 1) {
            $raw = [$raw[0], $raw[0]];
        }

        // align $times the same way labels/series are aligned — duplicate
        // a single sample so tooltip index lookups stay safe.
        $alignedTimes = $this->times;
        if (count($alignedTimes) === 1) {
            $alignedTimes = [$alignedTimes[0], $alignedTimes[0]];
        }

        return [
            'card' => [
                'label' => trans('server/console.labels.cpu'),
                'unit' => '%',
                'current' => number_format($this->getCurrentValue(), 1) . '%',
                'allocation' => number_format($this->getMaxValue(), 0) . '%',
                'progress' => [
                    'value' => $this->getCurrentValue(),
                    'max' => $this->getMaxValue(),
                    'colour' => $this->getProgressColor(),
                ],
                'ticks' => array_map(fn (float $v) => number_format($v, 0) . '%', $ticks),
                'series' => ResourceCard::points($this->series, $ticks[0], $ticks[2]),
                'labels' => array_map(fn (float $v) => number_format($v, 1) . '%', $raw),
                'times' => $alignedTimes,
                'axisTicks' => $this->axisTicks,
            ],
        ];
    }
}
