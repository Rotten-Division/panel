<?php

namespace App\Tests\Integration;

use App\Events\ActivityLogged;
use App\Tests\Assertions\AssertsActivityLogged;
use App\Tests\TestCase;
use App\Tests\Traits\Integration\CreatesTestModels;
use App\Transformers\Api\Application\BaseTransformer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

abstract class IntegrationTestCase extends TestCase
{
    use AssertsActivityLogged;
    use CreatesTestModels;

    // RefreshDatabase migrates a fresh schema per test, which is the safe
    // combo with :memory: sqlite enforced by tests/bootstrap.php. swapping
    // to DatabaseTruncation would either require a persisted testing.sqlite
    // or risk the connection cycling and dropping the schema mid-suite.
    use RefreshDatabase;

    protected $seed = true;

    protected $defaultHeaders = [
        'Accept' => 'application/json',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake(ActivityLogged::class);
    }

    /**
     * Return an ISO-8601 formatted timestamp to use in the API response.
     */
    protected function formatTimestamp(string $timestamp): string
    {
        return CarbonImmutable::createFromFormat(CarbonInterface::DEFAULT_TO_STRING_FORMAT, $timestamp)
            ->setTimezone(BaseTransformer::RESPONSE_TIMEZONE)
            ->toAtomString();
    }

    /**
     * The database connections that should have transactions.
     *
     * @return array
     */
    protected function connectionsToTransact()
    {
        return [DB::getDriverName()];
    }
}
