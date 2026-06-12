<?php

namespace App\Tests\Integration\Services\Servers;

use App\Models\Allocation;
use App\Services\Servers\PortClaim;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PortClaimTest extends IntegrationTestCase
{
    public function test_it_runs_the_closure_and_returns_its_value(): void
    {
        $out = (new PortClaim())->withClaims([25565], fn () => 'done');

        $this->assertSame('done', $out);
    }

    public function test_the_closure_runs_in_a_transaction_that_rolls_back_on_throw(): void
    {
        $server = $this->createServerModel();

        try {
            (new PortClaim())->withClaims([25565], function () use ($server) {
                Allocation::factory()->create(['node_id' => $server->node_id, 'port' => 25565]);
                throw new RuntimeException('boom');
            });
            $this->fail('expected the throw to propagate');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('allocations', ['port' => 25565]);
    }

    public function test_locks_release_after_the_call(): void
    {
        (new PortClaim())->withClaims([25565, 25565, 25564], fn () => null);

        // a lock on the same port is immediately acquirable again
        $this->assertTrue(Cache::lock('ospite:port-reserve:25565', 5)->get());
    }
}
