<?php

namespace App\Support;

use Carbon\Carbon;

class ResourceCard
{
    /**
     * Y-axis ticks for a normalised series. Returns [top, mid, bot].
     * Top expands the max by 15% so the line never touches the top edge,
     * bot drops the min by 20% so the line never grazes the bottom either.
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
     * Map raw samples to (x, y) coordinates inside the chart drawing area.
     * Width and height are the canonical numbers the blade view uses, so
     * paths look identical regardless of the actual rendered size.
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

    /**
     * Threshold colour for the utilisation bar. 60% switches to honey,
     * 85% to brick.
     */
    public static function progressColour(float $percent): string
    {
        return match (true) {
            $percent >= 85 => 'brick-fg',
            $percent >= 60 => 'honey',
            default => 'moss-fg',
        };
    }

    /**
     * Format the timestamps of the last $period samples from a Unix-second
     * keyed cache as HH:MM:SS strings in the supplied timezone. Returns an
     * empty array when the cache is empty.
     *
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
     * Pick three evenly spaced axis-tick labels from a pre-formatted
     * sample-times array (output of formatSampleTimes()). Returns three
     * em-dashes when the array is empty.
     *
     * @param  array<int, string>  $sampleTimes
     * @return array{0: string, 1: string, 2: string}
     */
    public static function pickAxisTicks(array $sampleTimes): array
    {
        if (empty($sampleTimes)) {
            return ['—', '—', '—'];
        }

        $count = count($sampleTimes);
        if ($count === 1) {
            return [$sampleTimes[0], $sampleTimes[0], $sampleTimes[0]];
        }

        return [
            $sampleTimes[0],
            $sampleTimes[intdiv($count - 1, 2)],
            $sampleTimes[$count - 1],
        ];
    }

    /**
     * @deprecated replaced by formatSampleTimes() + pickAxisTicks().
     *   docs/superpowers/plans/2026-05-18-resource-card-x-axis-timestamps
     *
     * @param  array<int, float|int>  $rawCache  cache keyed by unix timestamp
     */
    public static function formatTimeWindow(array $rawCache, int $period): string
    {
        $sliced = array_slice($rawCache, -$period, null, preserve_keys: true);
        if (count($sliced) < 2) {
            return 'earlier';
        }

        $timestamps = array_keys($sliced);
        $span = (int) end($timestamps) - (int) reset($timestamps);
        if ($span <= 0) {
            return 'earlier';
        }

        if ($span < 60) {
            return "{$span}s ago";
        }
        if ($span < 3600) {
            $minutes = (int) round($span / 60);

            return "{$minutes}m ago";
        }
        $hours = (int) round($span / 3600);

        return "{$hours}h ago";
    }

    /**
     * Auto-scale a bytes-per-second rate to the right unit. Returns the
     * formatted value (one decimal place) and the unit string.
     *
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
     * Render a byte rate using a fixed unit so the y-axis labels stay
     * consistent across the three ticks. The top tick picks the unit,
     * mid and bot use the same one.
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
