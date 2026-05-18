<?php

use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use App\Models\Server;
use App\Tests\TestCase;

uses(TestCase::class);

// these widgets cache by server id; use unique ids per test so cache
// state doesn't leak between cases.

test('CPU chart emits a labels array aligned with the series', function () {
    cache()->put('servers.9001.cpu_absolute', [
        1700000000 => 12.5,
        1700000001 => 73.0,
        1700000002 => 41.2,
    ]);

    $widget = new ServerCpuChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9001;
        $s->cpu = 200;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['labels'])->toBe(['12.5%', '73.0%', '41.2%']);
    expect(count($data['card']['labels']))->toBe(count($data['card']['series']));
});

test('Memory chart formats labels in GiB with two decimals', function () {
    cache()->put('servers.9002.memory_bytes', [
        1700000000 => 1.5 * 1024 ** 3,
        1700000001 => 0.62 * 1024 ** 3,
    ]);

    $widget = new ServerMemoryChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9002;
        $s->memory = 4096;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['labels'])->toBe(['1.50 GiB', '0.62 GiB']);
});

test('Network chart emits in/out labels prefixed with direction arrows', function () {
    cache()->put('servers.9003.network', [
        1700000000 => (object) ['rx_bytes' => 0,    'tx_bytes' => 0],
        1700000001 => (object) ['rx_bytes' => 2048, 'tx_bytes' => 1024],
        1700000002 => (object) ['rx_bytes' => 4096, 'tx_bytes' => 1536],
    ]);

    $widget = new ServerNetworkChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9003;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['labels'])->toHaveCount(count($data['card']['series']));
    expect($data['card']['labels2'])->toHaveCount(count($data['card']['series2']));
    expect($data['card']['labels'][0])->toStartWith('↓ ');
    expect($data['card']['labels2'][0])->toStartWith('↑ ');
});

test('network single-sample series duplicates in/out labels independently', function () {
    // two cumulative samples produce a single delta sample after the
    // refreshSeries diff. labels arrays must then mirror points()'s
    // single-sample duplication so the tooltip index lookup stays safe.
    cache()->put('servers.9005.network', [
        1700000000 => (object) ['rx_bytes' => 0,    'tx_bytes' => 0],
        1700000001 => (object) ['rx_bytes' => 4096, 'tx_bytes' => 2048],
    ]);

    $widget = new ServerNetworkChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9005;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect(count($data['card']['series']))->toBe(2);
    expect(count($data['card']['series2']))->toBe(2);
    expect(count($data['card']['labels']))->toBe(2);
    expect(count($data['card']['labels2']))->toBe(2);
    expect($data['card']['labels'][0])->toBe($data['card']['labels'][1]);
    expect($data['card']['labels2'][0])->toBe($data['card']['labels2'][1]);
});

test('CPU chart emits per-sample times in user timezone', function () {
    cache()->put('servers.9101.cpu_absolute', [
        1700000000 => 10.0,
        1700000005 => 11.0,
        1700000010 => 12.0,
    ]);

    $widget = new ServerCpuChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9101;
        $s->cpu = 200;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['times'])->toBe(['22:13:20', '22:13:25', '22:13:30']);
});

test('Memory chart emits per-sample times in user timezone', function () {
    cache()->put('servers.9102.memory_bytes', [
        1700000000 => 1.5 * 1024 ** 3,
        1700000005 => 1.7 * 1024 ** 3,
    ]);

    $widget = new ServerMemoryChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9102;
        $s->memory = 4096;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['times'])->toBe(['22:13:20', '22:13:25']);
});

test('Network chart aligns delta times to drop the first sample', function () {
    // three raw samples → two deltas; times should be the timestamps
    // of samples [1] and [2].
    cache()->put('servers.9103.network', [
        1700000000 => (object) ['rx_bytes' => 0,    'tx_bytes' => 0],
        1700000005 => (object) ['rx_bytes' => 1024, 'tx_bytes' => 512],
        1700000010 => (object) ['rx_bytes' => 2048, 'tx_bytes' => 768],
    ]);

    $widget = new ServerNetworkChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9103;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['times'])->toBe(['22:13:25', '22:13:30']);
});

test('Network single-delta chart duplicates the time entry for stable lookup', function () {
    cache()->put('servers.9104.network', [
        1700000000 => (object) ['rx_bytes' => 0,    'tx_bytes' => 0],
        1700000005 => (object) ['rx_bytes' => 1024, 'tx_bytes' => 512],
    ]);

    $widget = new ServerNetworkChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9104;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['times'])->toBe(['22:13:25', '22:13:25']);
});

test('chart with empty cache emits empty times', function () {
    cache()->forget('servers.9105.cpu_absolute');

    $widget = new ServerCpuChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9105;
        $s->cpu = 200;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    expect($data['card']['times'])->toBe([]);
});

test('single-sample series duplicates the label so indexes stay aligned', function () {
    cache()->put('servers.9004.cpu_absolute', [1700000000 => 50.0]);

    $widget = new ServerCpuChart();
    $widget->server = tap(new Server(), function ($s) {
        $s->id = 9004;
        $s->cpu = 200;
    });
    $widget->mount();
    $data = (fn () => $this->getViewData())->call($widget);

    // points() duplicates a single sample to render a flat line;
    // labels must mirror that or the tooltip would lookup undefined.
    expect(count($data['card']['series']))->toBe(2);
    expect(count($data['card']['labels']))->toBe(2);
    expect($data['card']['labels'])->toBe(['50.0%', '50.0%']);
});
