<?php

use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('renders one card per array entry with label and value', function () {
    $cards = [
        ['label' => 'CPU', 'value' => '2 cores'],
        ['label' => 'RAM', 'value' => '4 GiB'],
        ['label' => 'Disk', 'value' => '10 GiB'],
    ];

    $rendered = $this->blade('<x-overview.fact-cards :cards="$cards" />', ['cards' => $cards]);

    $rendered->assertSee('CPU');
    $rendered->assertSee('2 cores');
    $rendered->assertSee('RAM');
    $rendered->assertSee('4 GiB');
    $rendered->assertSee('Disk');
    $rendered->assertSee('10 GiB');
});

test('honours per-card variant class', function () {
    $cards = [
        ['label' => 'Cost', 'value' => '90%', 'variant' => 'warn'],
    ];

    $rendered = $this->blade('<x-overview.fact-cards :cards="$cards" />', ['cards' => $cards]);

    $rendered->assertSee('overview-fact-card--warn', escape: false);
});

test('renders empty grid when no cards', function () {
    $rendered = $this->blade('<x-overview.fact-cards :cards="[]" />');

    $rendered->assertSee('overview-fact-cards', escape: false);
    $rendered->assertDontSee('overview-fact-card__label', escape: false);
});
