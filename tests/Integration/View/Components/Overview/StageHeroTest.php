<?php

use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('renders title and body slots with variant class', function () {
    $rendered = $this->blade(<<<'BLADE'
<x-overview.stage-hero variant="stashed">
    <x-slot:title>In cold storage</x-slot:title>
    <x-slot:body>This server has been moved to long-term storage.</x-slot:body>
</x-overview.stage-hero>
BLADE);

    $rendered->assertSee('In cold storage');
    $rendered->assertSee('This server has been moved to long-term storage.');
    $rendered->assertSee('overview-stage-hero--stashed', escape: false);
});

test('renders cta slot when provided', function () {
    $rendered = $this->blade(<<<'BLADE'
<x-overview.stage-hero variant="retrieve">
    <x-slot:title>t</x-slot:title>
    <x-slot:body>b</x-slot:body>
    <x-slot:cta><button>Wake server</button></x-slot:cta>
</x-overview.stage-hero>
BLADE);

    $rendered->assertSee('Wake server');
    $rendered->assertSee('overview-stage-hero__cta', escape: false);
});

test('renders illustration slot when provided', function () {
    $rendered = $this->blade(<<<'BLADE'
<x-overview.stage-hero variant="stash">
    <x-slot:title>t</x-slot:title>
    <x-slot:body>b</x-slot:body>
    <x-slot:illustration><svg data-test="art"></svg></x-slot:illustration>
</x-overview.stage-hero>
BLADE);

    $rendered->assertSee('data-test="art"', escape: false);
    $rendered->assertSee('overview-stage-hero__illustration', escape: false);
});

test('omits illustration when slot is empty', function () {
    $rendered = $this->blade(<<<'BLADE'
<x-overview.stage-hero variant="suspended">
    <x-slot:title>t</x-slot:title>
    <x-slot:body>b</x-slot:body>
</x-overview.stage-hero>
BLADE);

    $rendered->assertDontSee('overview-stage-hero__illustration', escape: false);
});
