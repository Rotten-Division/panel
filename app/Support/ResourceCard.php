<?php

namespace App\Support;

use Carbon\Carbon;

class ResourceCard
{
    /**
     * top pads the max by 15% and bot drops the min by 20% so the plotted
     * line never touches either edge.
     *
     * @param  array<int, float|int>  $series
     * @return array<int, float>
     */
    public static function ticks(array $series): array
    {
        if (empty($series)) {
            return [1.0, 0.5, 0.0];
        }

        $max = (float) max($series);
        $min = (float) min($series);
        $top = $max * 1.15;
        $bot = max(0.0, $min * 0.8);

        if ($top <= $bot) {
            $top = $bot + 1.0;
        }

        $mid = ($top + $bot) / 2;

        return [$top, $mid, $bot];
    }

    /**
     * width and height are the canonical numbers the blade view uses, so the
     * generated paths look identical regardless of the rendered size.
     *
     * @param  array<int, float|int>  $series
     * @return array<int, array{0: float, 1: float}>
     */
    public static function points(array $series, float $top, float $bot, int $width = 320, int $chartTop = 8, int $chartHeight = 80): array
    {
        $count = count($series);
        if ($count === 0) {
            return [];
        }
        if ($count === 1) {
            // single point can't draw a line, duplicate to render a flat segment.
            $series = [$series[0], $series[0]];
            $count = 2;
        }

        $range = $top - $bot;
        if ($range <= 0) {
            $range = 1.0;
        }

        $points = [];
        foreach (array_values($series) as $i => $value) {
            $x = (float) (($i / ($count - 1)) * $width);
            $normalised = ((float) $value - $bot) / $range;
            $y = (float) ($chartTop + $chartHeight - ($normalised * $chartHeight));
            $points[] = [$x, $y];
        }

        return $points;
    }

    public static function progressColour(float $percent): string
    {
        return match (true) {
            $percent >= 85 => 'brick-fg',
            $percent >= 60 => 'honey',
            default => 'moss-fg',
        };
    }

    /**
     * @param  array<int|string, mixed>  $rawCache  cache keyed by unix timestamp
     * @return array<int, string> ordered HH:MM:SS strings
     */
    public static function formatSampleTimes(array $rawCache, int $period, ?string $timezone = null): array
    {
        if (empty($rawCache)) {
            return [];
        }

        $tz = $timezone ?: 'UTC';
        $sliced = array_slice($rawCache, -$period, null, preserve_keys: true);

        return array_map(
            fn (int|string $unix) => Carbon::createFromTimestamp((int) $unix, $tz)->format('H:i:s'),
            array_keys($sliced),
        );
    }

    /**
     * @return array{value: string, unit: string}
     */
    public static function formatRate(int $bytesPerSecond): array
    {
        if ($bytesPerSecond < 1024) {
            return ['value' => (string) $bytesPerSecond, 'unit' => 'B/s'];
        }
        if ($bytesPerSecond < 1024 * 1024) {
            return ['value' => number_format($bytesPerSecond / 1024, 1), 'unit' => 'KiB/s'];
        }
        if ($bytesPerSecond < 1024 * 1024 * 1024) {
            return ['value' => number_format($bytesPerSecond / 1024 / 1024, 1), 'unit' => 'MiB/s'];
        }

        return ['value' => number_format($bytesPerSecond / 1024 / 1024 / 1024, 1), 'unit' => 'GiB/s'];
    }

    /**
     * fixed unit instead of auto-scaling, so the three y-axis ticks all read
     * in the same unit. the top tick picks it, mid and bot follow.
     */
    public static function formatRateInUnit(int $bytesPerSecond, string $unit): string
    {
        $value = match ($unit) {
            'GiB/s' => $bytesPerSecond / 1024 / 1024 / 1024,
            'MiB/s' => $bytesPerSecond / 1024 / 1024,
            'KiB/s' => $bytesPerSecond / 1024,
            default => (float) $bytesPerSecond,
        };

        return number_format($value, $unit === 'B/s' ? 0 : 1) . ' ' . $unit;
    }
}
