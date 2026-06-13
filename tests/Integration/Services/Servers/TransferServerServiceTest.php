<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\NodeRoutableGate;
use App\Exceptions\DisplayException;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\Nodes\NodeJWTService;
use App\Services\Servers\PortClaim;
use App\Services\Servers\TransferServerService;
use App\Tests\Integration\IntegrationTestCase;
use Closure;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\UnencryptedToken;

// the destination bind goes through the claim asserting the source is bound-by-self
// and the destination row is free. transfer cannot use for(port)===Free for the
// destination because the port is Bound on the source mid-move, so the precondition
// is "bound by this server" on the source.
//
// sqlite has no real FOR UPDATE, so cross-process exclusion is proven on canary mysql
// against a real database, not sqlite.
class TransferServerServiceTest extends IntegrationTestCase
{
    public function test_transfer_rebinds_the_same_port_on_the_destination_node(): void
    {
        $server = $this->createServerModel(['memory' => 128, 'disk' => 128, 'cpu' => 0]);
        $sourcePort = (int) $server->allocation->port;

        $destination = Node::factory()->create(['memory' => 1024, 'disk' => 1024, 'cpu' => 0]);
        $destAllocation = Allocation::factory()->create([
            'node_id' => $destination->id,
            'port' => $sourcePort,
            'server_id' => null,
        ]);

        $this->mockJwt();

        $this->getService()->handle($server, $destination->id, $destAllocation->id);

        // the destination row is now bound to the transferring server, same port.
        $this->assertDatabaseHas('allocations', ['id' => $destAllocation->id, 'server_id' => $server->id]);
    }

    public function test_transfer_to_a_peerless_node_is_refused(): void
    {
        $server = $this->createServerModel(['memory' => 128, 'disk' => 128, 'cpu' => 0]);
        $sourcePort = (int) $server->allocation->port;

        $destination = Node::factory()->create(['memory' => 1024, 'disk' => 1024, 'cpu' => 0]);
        $destAllocation = Allocation::factory()->create([
            'node_id' => $destination->id,
            'port' => $sourcePort,
            'server_id' => null,
        ]);

        // the destination node has no routing peer.
        $gate = \Mockery::mock(NodeRoutableGate::class);
        $gate->shouldReceive('routable')->with($destination->id)->andReturnFalse();
        $gate->shouldReceive('routable')->andReturnTrue();
        $this->swap(NodeRoutableGate::class, $gate);

        $this->expectException(DisplayException::class);

        try {
            $this->getService()->handle($server, $destination->id, $destAllocation->id);
        } finally {
            $this->assertDatabaseHas('allocations', ['id' => $destAllocation->id, 'server_id' => null]);
        }
    }

    public function test_transfer_aborts_when_a_destination_is_claimed_during_the_lock(): void
    {
        $server = $this->createServerModel(['memory' => 128, 'disk' => 128, 'cpu' => 0]);
        $sourcePort = (int) $server->allocation->port;

        $destination = Node::factory()->create(['memory' => 1024, 'disk' => 1024, 'cpu' => 0]);
        $other = $this->createServerModel();
        $destAllocation = Allocation::factory()->create([
            'node_id' => $destination->id,
            'port' => $sourcePort,
            'server_id' => null,
        ]);

        // simulate the race the fresh-read-under-lock closes: the destination is free at
        // the pre-filter, but another claimant binds it before this transfer's closure
        // runs. a stale pre-lock snapshot would overwrite the winner; the re-read inside
        // the claim must catch it and abort, binding nothing.
        $this->swap(PortClaim::class, new class($destAllocation->id, $other->id) extends PortClaim
        {
            public function __construct(private int $stolenId, private int $winnerId) {}

            public function withClaims(array $ports, Closure $bind): mixed
            {
                Allocation::query()->where('id', $this->stolenId)->update(['server_id' => $this->winnerId]);

                return $bind();
            }
        });

        $this->mockJwt();
        $this->expectException(DisplayException::class);
        // assert the guard's own message, not just any DisplayException: this
        // distinguishes the "no longer free" re-read guard firing from a generic rollback.
        $this->expectExceptionMessage("Destination allocation {$destAllocation->id} is no longer free.");

        try {
            $this->getService()->handle($server, $destination->id, $destAllocation->id);
        } finally {
            // the guard fired (DisplayException), so the transferring server bound nothing.
            // single-process, the simulated winner's write rolls back with the aborted
            // outer transaction (a real winner is a separate committed transaction), so the
            // observable correctness here is that the transfer did not steal the row.
            $this->assertDatabaseMissing('allocations', ['id' => $destAllocation->id, 'server_id' => $server->id]);
        }
    }

    private function mockJwt(): void
    {
        $jwt = \Mockery::mock(NodeJWTService::class);
        $jwt->shouldReceive('setExpiresAt')->andReturnSelf();
        $jwt->shouldReceive('setSubject')->andReturnSelf();
        $jwt->shouldReceive('handle')->andReturn(\Mockery::mock(UnencryptedToken::class, ['toString' => 'token']));
        $this->swap(NodeJWTService::class, $jwt);

        Http::fake();
    }

    private function getService(): TransferServerService
    {
        return $this->app->make(TransferServerService::class);
    }
}
