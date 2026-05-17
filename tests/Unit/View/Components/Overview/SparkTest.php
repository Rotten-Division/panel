<?php

use App\Tests\TestCase;
use Illuminate\Support\Facades\Blade;

// rendering tests need the laravel container for the Blade facade.
uses(TestCase::class);

test('spark renders title and value', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" value="12.4%" :series="[0.1, 0.2, 0.3]" />'
    );

    expect($rendered)->toContain('CPU');
    expect($rendered)->toContain('12.4%');
});

test('spark applies the muted modifier class when muted prop is true', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" value="—" :series="[]" :muted="true" />'
    );

    expect($rendered)->toContain('overview-spark--muted');
});

test('spark applies the color modifier class', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="Memory" :series="[0.1, 0.5]" color="moss" />'
    );

    expect($rendered)->toContain('overview-spark--moss');
});

test('spark produces a single-space-separated class chain via @class directive', function () {
    // earlier regression: @if($muted) ... @endif left a double space which
    // broke any test or query expecting clean class composition.
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" value="—" :series="[]" :muted="true" />'
    );

    expect($rendered)->toContain('class="overview-spark overview-spark--hearth overview-spark--muted"');
    expect($rendered)->not->toContain('  overview-spark');
});

test('spark survives a single-value series by duplicating the point', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="Memory" :series="[1.5]" />'
    );

    // single point → duplicated to two identical points → flat line at value
    expect($rendered)->toContain('M0.0,');
});

test('spark omits the chart path entirely when series is empty', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" :series="[]" />'
    );

    // svg shell renders so the card height stays consistent, but no path
    // inside (the area + line are gated behind a non-empty path string)
    expect($rendered)->toContain('overview-spark__svg');
    expect($rendered)->not->toContain('<path');
});

test('spark defaults to hearth colour when no color prop supplied', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" :series="[0.5]" />'
    );

    expect($rendered)->toContain('overview-spark--hearth');
});

test('spark accepts a custom height', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" :series="[0.5]" :height="120" />'
    );

    expect($rendered)->toContain('viewBox="0 0 240 120"');
});

test('spark omits value tag when value prop is null', function () {
    $rendered = Blade::render(
        '<x-overview.spark title="CPU" :series="[0.5]" />'
    );

    // no <b> tag because $value defaults to null
    expect($rendered)->not->toContain('<b class="font-mono">');
});
