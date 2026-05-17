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

    /** rolling-window label for the left x-axis tick, e.g. "30s ago".
     *  computed from the actual cache timestamps so the chart never lies
     *  about how far back the data goes. */
    public string $windowLabel = 'earlier';

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
        $this->refreshSeries();
    }

    #[On('refresh-overview')]
    public function refreshSeries(): void
    {
        $period = (int) (user()?->getCustomization(CustomizationKey::ConsoleGraphPeriod) ?? 30);
        $raw = cache()->get("servers.{$this->server?->id}.cpu_absolute") ?? [];
        $this->series = collect($raw)
            ->slice(-$period)
            ->map(fn ($value) => (float) round($value, 2))
            ->values()
            ->all();
        $this->windowLabel = ResourceCard::formatTimeWindow($raw, $period);
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
                'windowLabel' => $this->windowLabel,
            ],
        ];
    }
}
