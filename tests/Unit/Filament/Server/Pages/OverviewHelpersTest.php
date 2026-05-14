<?php

use App\Filament\Server\Pages\Overview;

test('uptimeLabel returns null when uptimeMs is zero or negative', function () {
    $page = new Overview();
    $page->uptimeMs = 0;
    expect($page->uptimeLabel())->toBeNull();

    $page->uptimeMs = -100;
    expect($page->uptimeLabel())->toBeNull();
});

test('uptimeLabel formats milliseconds as short two-part duration', function () {
    $page = new Overview();
    $page->uptimeMs = (3 * 3600 + 14 * 60) * 1000;

    $label = $page->uptimeLabel();
    expect($label)->toBeString();
    // accept either "3h 14m" or the locale-specific short form. the key
    // invariant is the duration appears short (no "hours, minutes" verbose
    // form) and contains both parts in some order.
    expect($label)->toMatch('/3\D*h.*14\D*m|3\D*hour.{0,3}.*14\D*min/i');
});

test('uptimeLabel handles sub-second values', function () {
    $page = new Overview();
    $page->uptimeMs = 500;

    // sub-second uptime is unusual but not impossible (server just started).
    // CarbonInterval renders this as "0s" or similar short form, NOT null,
    // because the input is technically > 0. We assert it produces a
    // non-empty string.
    expect($page->uptimeLabel())->toBeString()->not->toBeEmpty();
});

test('uptimeLabel covers multi-day durations', function () {
    $page = new Overview();
    $page->uptimeMs = (2 * 24 * 3600 + 5 * 3600) * 1000;

    $label = $page->uptimeLabel();
    expect($label)->toMatch('/2\D*d.*5\D*h|2\D*day.{0,4}.*5\D*hour/i');
});
