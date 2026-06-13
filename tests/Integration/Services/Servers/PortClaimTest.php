<?php

namespace App\Tests\Integration\Services\Servers;

use App\Models\Allocation;
use App\Services\Servers\PortClaim;
use App\Tests\Integration\IntegrationTestCase;
use RuntimeException;

// sqlite has no real FOR UPDATE, so cross-process exclusion is proven on canary mysql
// against a real database; here the helper runs cleanly with the lock a no-op.
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
}
