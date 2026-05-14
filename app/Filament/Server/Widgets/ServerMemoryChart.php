<?php

namespace App\Filament\Server\Widgets;

use App\Enums\CustomizationKey;
use App\Models\Server;
use App\Support\ResourceCard;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

class ServerMemoryChart extends Widget
{
    protected string $view = 'filament.server.widgets.resource-card';

    public ?Server $server = null;

    /** @var array<int, float> */
    public array $series = [];

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState() && !$server->retrieveStatus()->isOffline();
    }

    public function mount(): void
    {
        $this->refreshSeries();
    }

    public function refreshSeries(): void
    {
        $period = (int) (user()?->getCustomization(CustomizationKey::ConsoleGraphPeriod) ?? 30);
        $divisor = config('panel.use_binary_prefix') ? 1024 * 1024 * 1024 : 1_000_000_000;

        $this->series = collect(cache()->get("servers.{$this->server?->id}.memory_bytes") ?? [])
            ->slice(-$period)
            ->map(fn ($value) => (float) round($value / $divisor, 2))
            ->values()
            ->all();
    }

    public function getCurrentValue(): float
    {
        return empty($this->series) ? 0.0 : (float) end($this->series);
    }

    public function getMaxValue(): float
    {
        // server limit is stored in MiB. zero means unlimited so the bar
        // falls back to a max derived from the data itself.
        $limit = (float) ($this->server?->memory ?? 0);
        if ($limit > 0) {
            return $limit / 1024;
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
                'label' => trans('server/console.labels.memory'),
                'unit' => 'GiB',
                'current' => number_format($this->getCurrentValue(), 2) . ' GiB',
                'allocation' => number_format($this->getMaxValue(), 1) . ' GiB',
                'progress' => [
                    'value' => $this->getCurrentValue(),
                    'max' => $this->getMaxValue(),
                    'colour' => $this->getProgressColor(),
                ],
                'ticks' => array_map(fn (float $v) => number_format($v, 1) . ' G', $ticks),
                'series' => ResourceCard::points($this->series, $ticks[0], $ticks[2]),
            ],
        ];
    }
}
