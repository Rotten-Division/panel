<?php

namespace App\Tests;

use App\Tests\Seeders\EggSeeder;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // belt-and-suspenders for phpunit.xml: if a real env var (e.g.
        // DB_CONNECTION=pgsql baked into the docker container) wins over
        // the force="true" directives, DatabaseTruncation wipes prod tables
        // on the first test run. refuse to boot the suite in that case.
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.$connection.driver");
        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                "tests must run against sqlite, got driver={$driver} for connection={$connection}. "
                .'check phpunit.xml <env> force="true" attributes and the docker env. '
                .'if this fires in CI, do NOT remove this guard — fix the env leak.'
            );
        }
        $database = (string) config("database.connections.$connection.database");
        if ($database !== ':memory:' && !str_contains($database, 'testing')) {
            throw new RuntimeException(
                "tests must run against :memory: or a testing.sqlite file, got database={$database}."
            );
        }

        Carbon::setTestNow(Carbon::now());
        CarbonImmutable::setTestNow(Carbon::now());

        // Why, you ask? If we don't force this to false it is possible for certain exceptions
        // to show their error message properly in the integration test output, but not actually
        // be setup correctly to display their message in production.
        //
        // If we expect a message in a test, and it isn't showing up (rather, showing the generic
        // "an error occurred" message), we can probably assume that the exception isn't one that
        // is recognized as being user viewable.
        config()->set('app.debug', false);
        config()->set('panel.auth.2fa_required', 0);

        $this->setKnownUuidFactory();

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();

        try {
            $seeder = new EggSeeder();
            $seeder->run();
        } catch (Exception) {
            // Don't fail all tests if the fixture/ seeder isn't present or import fails.
        }
    }

    /**
     * Tear down tests.
     */
    protected function tearDown(): void
    {
        restore_exception_handler();
        restore_error_handler();

        parent::tearDown();

        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }

    /**
     * Handles the known UUID handling in certain unit tests. Use the "MocksUuid" trait
     * in order to enable this ability.
     */
    public function setKnownUuidFactory()
    {
        // do nothing
    }
}
