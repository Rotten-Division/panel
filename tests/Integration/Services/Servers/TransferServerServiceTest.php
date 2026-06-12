<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\NodeRoutableGate;
use App\Exceptions\DisplayException;
use App\Models\Allocation;
use App\Models\Node;
use App\Services\Nodes\NodeJWTService;
use App\Services\Servers\TransferServerService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\UnencryptedToken;

// the destination bind goes through the claim asserting the source is bound-by-self
// and the destination row is free. transfer cannot use for(port)===Free for the
// destination because the port is Bound on the source mid-move, so the precondition
// is "bound by this server" on the source.
//
// the fence is tested single-process: sqlite (see phpunit.xml) has no real FOR
// UPDATE and isolates each connection, so a true two-process concurrency test is not
// faithful. real cross-process FOR UPDATE exclusion is proven on canary mysql (phase
// 7), not sqlite.
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
