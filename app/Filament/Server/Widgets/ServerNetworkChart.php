<?php

namespace App\Filament\Server\Widgets;

use App\Enums\CustomizationKey;
use App\Models\Server;
use App\Support\ResourceCard;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class ServerNetworkChart extends Widget
{
    protected string $view = 'filament.server.widgets.resource-card';

    public ?Server $server = null;

    /** @var array<int, int> */
    public array $inboundSeries = [];

    /** @var array<int, int> */
    public array $outboundSeries = [];

    /** @var array<int, string> */
    public array $times = [];

    public bool $frozen = false;

    public static function canView(): bool
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return !$server->isInConflictState();
    }

    public function mount(): void
    {
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
        $raw = cache()->get("servers.{$this->server?->id}.network") ?? [];
        $samples = collect($raw)
            ->slice(-$period)
            ->values()
            ->all();

        $inbound = [];
        $outbound = [];
        $previous = null;

        foreach ($samples as $current) {
            if ($previous !== null) {
                $inbound[] = max(0, (int) ($current->rx_bytes - $previous->rx_bytes));
                $outbound[] = max(0, (int) ($current->tx_bytes - $previous->tx_bytes));
            }
            $previous = $current;
        }

        $this->inboundSeries = $inbound;
        $this->outboundSeries = $outbound;

        // align timestamps with the deltas, drop the first sample's
        // time since no delta exists for index 0.
        $allTimes = ResourceCard::formatSampleTimes($raw, $period, $tz);
        $this->times = array_values(array_slice($allTimes, 1));
    }

    public function getCurrentInbound(): int
    {
        return empty($this->inboundSeries) ? 0 : (int) end($this->inboundSeries);
    }

    public function getCurrentOutbound(): int
    {
        return empty($this->outboundSeries) ? 0 : (int) end($this->outboundSeries);
    }

    /**
     * Y-axis ticks span the union of both series so the dashed outbound
     * line stays in scale with inbound.
     *
     * @return array<int, int>
     */
    public function getYAxisTicks(): array
    {
        $merged = array_merge($this->inboundSeries, $this->outboundSeries);
        $ticks = ResourceCard::ticks(empty($merged) ? [0.0] : array_map('floatval', $merged));

        return array_map(fn ($v) => (int) $v, $ticks);
    }

    protected function getViewData(): array
    {
        $ticks = $this->getYAxisTicks();
        $topUnit = ResourceCard::formatRate($ticks[0]);
        $unit = $topUnit['unit'];

        $inboundRaw = $this->inboundSeries;
        $outboundRaw = $this->outboundSeries;
        if (count($inboundRaw) === 1) {
            $inboundRaw = [$inboundRaw[0], $inboundRaw[0]];
        }
        if (count($outboundRaw) === 1) {
            $outboundRaw = [$outboundRaw[0], $outboundRaw[0]];
        }

        $alignedTimes = $this->times;
        if (count($alignedTimes) === 1) {
            $alignedTimes = [$alignedTimes[0], $alignedTimes[0]];
        }

        return [
            'card' => [
                'label' => trans('server/console.labels.network'),
                'current' => '',
                'allocation' => null,
                'progress' => null,
                'series' => ResourceCard::points(
                    array_map('floatval', $this->inboundSeries),
                    (float) $ticks[0],
                    (float) $ticks[2],
                ),
                'series2' => ResourceCard::points(
                    array_map('floatval', $this->outboundSeries),
                    (float) $ticks[0],
                    (float) $ticks[2],
                ),
                'labels' => array_map(
                    fn (int $v) => '↓ ' . ResourceCard::formatRateInUnit($v, $unit),
                    $inboundRaw,
                ),
                'labels2' => array_map(
                    fn (int $v) => '↑ ' . ResourceCard::formatRateInUnit($v, $unit),
                    $outboundRaw,
                ),
                'times' => $alignedTimes,
                'legend' => [
                    'in' => ResourceCard::formatRate($this->getCurrentInbound()),
                    'out' => ResourceCard::formatRate($this->getCurrentOutbound()),
                ],
            ],
        ];
    }
}
