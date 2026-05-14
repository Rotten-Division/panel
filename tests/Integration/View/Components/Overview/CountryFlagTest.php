<?php

use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('renders GB flag for lowercase code', function () {
    $rendered = $this->blade('<x-overview.country-flag code="gb" />');
    $rendered->assertSee('viewBox="0 0 60 40"', escape: false);
    $rendered->assertSee('#012169', escape: false);
});

test('renders GB flag for uppercase code', function () {
    $rendered = $this->blade('<x-overview.country-flag code="GB" />');
    $rendered->assertSee('viewBox="0 0 60 40"', escape: false);
});

test('renders GB flag for UK alias', function () {
    $rendered = $this->blade('<x-overview.country-flag code="uk" />');
    $rendered->assertSee('viewBox="0 0 60 40"', escape: false);
});

test('renders placeholder for unknown country code', function () {
    $rendered = $this->blade('<x-overview.country-flag code="de" />');
    $rendered->assertSee('DE');
    $rendered->assertDontSee('viewBox="0 0 60 40"', escape: false);
});

test('uppercases placeholder code', function () {
    $rendered = $this->blade('<x-overview.country-flag code="zz" />');
    $rendered->assertSee('ZZ');
});
