<?php

use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use App\Models\Server;

test('CPU current value reads the last sample', function () {
    $widget = new ServerCpuChart();
    $widget->server = (new Server())->forceFill(['cpu' => 200]);
    $widget->series = [12.5, 18.0, 22.3];

    expect($widget->getCurrentValue())->toBe(22.3);
});

test('CPU current value is zero with no samples', function () {
    $widget = new ServerCpuChart();
    $widget->server = (new Server())->forceFill(['cpu' => 200]);
    $widget->series = [];

    expect($widget->getCurrentValue())->toBe(0.0);
});

test('CPU max returns server cap when set', function () {
    $widget = new ServerCpuChart();
    $widget->server = (new Server())->forceFill(['cpu' => 200]);
    $widget->series = [10.0];

    expect($widget->getMaxValue())->toBe(200.0);
});

test('CPU progress colour honours 60 and 85 thresholds', function () {
    $widget = new ServerCpuChart();
    $widget->server = (new Server())->forceFill(['cpu' => 100]);

    $widget->series = [30.0];
    expect($widget->getProgressColor())->toBe('moss-fg');

    $widget->series = [70.0];
    expect($widget->getProgressColor())->toBe('honey');

    $widget->series = [90.0];
    expect($widget->getProgressColor())->toBe('brick-fg');
});

test('Memory current value reads the last sample', function () {
    $widget = new ServerMemoryChart();
    $widget->server = (new Server())->forceFill(['memory' => 4096]);
    $widget->series = [1.2, 2.8, 3.5];

    expect($widget->getCurrentValue())->toBe(3.5);
});

test('Memory max converts MiB cap to GiB', function () {
    $widget = new ServerMemoryChart();
    $widget->server = (new Server())->forceFill(['memory' => 4096]);
    $widget->series = [1.0];

    expect($widget->getMaxValue())->toBe(4.0);
});

test('Memory progress colour switches at thresholds', function () {
    $widget = new ServerMemoryChart();
    $widget->server = (new Server())->forceFill(['memory' => 1024]);

    $widget->series = [0.3];
    expect($widget->getProgressColor())->toBe('moss-fg');

    $widget->series = [0.7];
    expect($widget->getProgressColor())->toBe('honey');

    $widget->series = [0.95];
    expect($widget->getProgressColor())->toBe('brick-fg');
});

test('Network current values read last samples', function () {
    $widget = new ServerNetworkChart();
    $widget->inboundSeries = [100, 200, 300];
    $widget->outboundSeries = [50, 75, 100];

    expect($widget->getCurrentInbound())->toBe(300);
    expect($widget->getCurrentOutbound())->toBe(100);
});

test('Network current values fall back to zero with no samples', function () {
    $widget = new ServerNetworkChart();

    expect($widget->getCurrentInbound())->toBe(0);
    expect($widget->getCurrentOutbound())->toBe(0);
});
