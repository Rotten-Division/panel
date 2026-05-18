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
