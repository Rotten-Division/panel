<?php

use App\Support\ResourceCard;

test('formatSampleTimes returns empty array on empty cache', function () {
    expect(ResourceCard::formatSampleTimes([], 30, 'UTC'))->toBe([]);
});

test('formatSampleTimes formats Unix timestamps as HH:MM:SS in UTC by default', function () {
    $cache = [
        1700000000 => 10.0,  // 2023-11-14 22:13:20 UTC
        1700000005 => 11.0,  // 22:13:25
        1700000010 => 12.0,  // 22:13:30
    ];

    expect(ResourceCard::formatSampleTimes($cache, 30, 'UTC'))->toBe([
        '22:13:20',
        '22:13:25',
        '22:13:30',
    ]);
});

test('formatSampleTimes shifts into the user timezone', function () {
    $cache = [
        1700000000 => 10.0,  // 2023-11-14 17:13:20 New York (EST, UTC-5)
    ];

    expect(ResourceCard::formatSampleTimes($cache, 30, 'America/New_York'))->toBe([
        '17:13:20',
    ]);
});

test('formatSampleTimes slices to the last $period entries', function () {
    $cache = [];
    for ($i = 0; $i < 50; $i++) {
        $cache[1700000000 + $i] = (float) $i;
    }

    $times = ResourceCard::formatSampleTimes($cache, 5, 'UTC');

    expect($times)->toHaveCount(5);
    expect($times[0])->toBe('22:14:05');   // 1700000000 + 45
    expect($times[4])->toBe('22:14:09');   // 1700000000 + 49
});

test('pickAxisTicks returns three em-dashes for empty input', function () {
    expect(ResourceCard::pickAxisTicks([]))->toBe(['—', '—', '—']);
});

test('pickAxisTicks duplicates a single sample across three ticks', function () {
    expect(ResourceCard::pickAxisTicks(['12:00:00']))->toBe(['12:00:00', '12:00:00', '12:00:00']);
});

test('pickAxisTicks picks oldest, middle, newest from a 30-sample window', function () {
    $times = [];
    for ($i = 0; $i < 30; $i++) {
        $times[] = sprintf('22:14:%02d', $i);
    }

    expect(ResourceCard::pickAxisTicks($times))->toBe([
        '22:14:00',
        '22:14:14',
        '22:14:29',
    ]);
});

test('pickAxisTicks biases left on even counts', function () {
    $times = ['00:00:00', '00:00:01', '00:00:02', '00:00:03'];

    expect(ResourceCard::pickAxisTicks($times))->toBe([
        '00:00:00',
        '00:00:01',
        '00:00:03',
    ]);
});
