<?php

use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('renders title and subtitle', function () {
    $rendered = $this->blade('<x-overview.state-banner variant="transient" title="Starting" subtitle="Server is booting" />');

    $rendered->assertSee('Starting');
    $rendered->assertSee('Server is booting');
    $rendered->assertSee('overview-banner--transient', escape: false);
});

test('omits subtitle markup when none provided', function () {
    $rendered = $this->blade('<x-overview.state-banner variant="default" title="Stopped" />');

    $rendered->assertSee('Stopped');
    $rendered->assertDontSee('overview-banner__subtitle', escape: false);
});

test('renders progress bar when showProgress is true', function () {
    $rendered = $this->blade('<x-overview.state-banner variant="transient" title="Loading" :show-progress="true" />');

    $rendered->assertSee('overview-banner__progress', escape: false);
});

test('skips progress bar by default', function () {
    $rendered = $this->blade('<x-overview.state-banner variant="default" title="Stopped" />');

    $rendered->assertDontSee('overview-banner__progress', escape: false);
});

test('default slot renders alongside title', function () {
    $rendered = $this->blade('<x-overview.state-banner variant="default" title="t">extra-content</x-overview.state-banner>');

    $rendered->assertSee('extra-content');
});
