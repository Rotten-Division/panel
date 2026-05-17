<?php

use App\Support\ResourceCard;

test('ticks expands max by 15 percent and trims min by 20 percent', function () {
    [$top, $mid, $bot] = ResourceCard::ticks([10.0, 20.0, 30.0]);

    expect($top)->toEqualWithDelta(30.0 * 1.15, 0.001);
    expect($bot)->toEqualWithDelta(10.0 * 0.8, 0.001);
    expect($mid)->toEqualWithDelta(($top + $bot) / 2, 0.001);
});

test('ticks returns sentinel range for empty series', function () {
    expect(ResourceCard::ticks([]))->toBe([1.0, 0.5, 0.0]);
});

test('ticks clamps min to zero when min is negative or zero', function () {
    [$top, , $bot] = ResourceCard::ticks([0.0, 5.0, 8.0]);

    expect($bot)->toBe(0.0);
    expect($top)->toBeGreaterThan($bot);
});

test('ticks separates top from bot when all samples are equal', function () {
    [$top, , $bot] = ResourceCard::ticks([5.0, 5.0, 5.0]);

    expect($top)->toBeGreaterThan($bot);
});

test('points scales samples across the chart width', function () {
    $points = ResourceCard::points([0.0, 50.0, 100.0], 100.0, 0.0, width: 100, chartTop: 0, chartHeight: 100);

    expect($points)->toHaveCount(3);
    expect($points[0][0])->toBe(0.0);
    expect($points[2][0])->toBe(100.0);
    // y inverts: 0 maps to chartTop+chartHeight, 100 maps to chartTop.
    expect($points[0][1])->toBe(100.0);
    expect($points[2][1])->toBe(0.0);
});

test('points duplicates a single sample to render a flat segment', function () {
    $points = ResourceCard::points([42.0], 50.0, 0.0);

    expect($points)->toHaveCount(2);
    expect($points[0][1])->toBe($points[1][1]);
});

test('progressColour honours 60 and 85 thresholds', function () {
    expect(ResourceCard::progressColour(0))->toBe('moss-fg');
    expect(ResourceCard::progressColour(59.9))->toBe('moss-fg');
    expect(ResourceCard::progressColour(60))->toBe('honey');
    expect(ResourceCard::progressColour(84.9))->toBe('honey');
    expect(ResourceCard::progressColour(85))->toBe('brick-fg');
    expect(ResourceCard::progressColour(100))->toBe('brick-fg');
});

test('formatRate scales bytes per second to the right unit', function () {
    expect(ResourceCard::formatRate(0))->toBe(['value' => '0', 'unit' => 'B/s']);
    expect(ResourceCard::formatRate(512))->toBe(['value' => '512', 'unit' => 'B/s']);
    expect(ResourceCard::formatRate(2048))->toBe(['value' => '2.0', 'unit' => 'KiB/s']);
    expect(ResourceCard::formatRate(2 * 1024 * 1024))->toBe(['value' => '2.0', 'unit' => 'MiB/s']);
    expect(ResourceCard::formatRate(3 * 1024 * 1024 * 1024))->toBe(['value' => '3.0', 'unit' => 'GiB/s']);
});

test('formatRateInUnit uses the supplied unit for all values', function () {
    expect(ResourceCard::formatRateInUnit(1024, 'KiB/s'))->toBe('1.0 KiB/s');
    expect(ResourceCard::formatRateInUnit(2 * 1024 * 1024, 'KiB/s'))->toBe('2,048.0 KiB/s');
    expect(ResourceCard::formatRateInUnit(0, 'B/s'))->toBe('0 B/s');
});

test('formatTimeWindow returns earlier for empty or single-sample cache', function () {
    expect(ResourceCard::formatTimeWindow([], 30))->toBe('earlier');
    expect(ResourceCard::formatTimeWindow([1700000000 => 1.0], 30))->toBe('earlier');
});

test('formatTimeWindow formats seconds when span under a minute', function () {
    $cache = [
        1700000000 => 1.0,
        1700000015 => 2.0,
        1700000030 => 3.0,
    ];
    expect(ResourceCard::formatTimeWindow($cache, 30))->toBe('30s ago');
});

test('formatTimeWindow rounds to minutes when span over a minute', function () {
    $cache = [
        1700000000 => 1.0,
        1700000300 => 2.0, // 5 min
    ];
    expect(ResourceCard::formatTimeWindow($cache, 30))->toBe('5m ago');
});

test('formatTimeWindow rounds to hours when span over an hour', function () {
    $cache = [
        1700000000 => 1.0,
        1700007200 => 2.0, // 2h
    ];
    expect(ResourceCard::formatTimeWindow($cache, 30))->toBe('2h ago');
});

test('formatTimeWindow respects the slice period — only counts the last N samples', function () {
    // 100 samples spanning 100s, but $period=10 means we only look at the
    // last 10 samples → ~10s window, not 100s.
    $cache = [];
    for ($i = 0; $i < 100; $i++) {
        $cache[1700000000 + $i] = (float) $i;
    }
    expect(ResourceCard::formatTimeWindow($cache, 10))->toBe('9s ago');
});
