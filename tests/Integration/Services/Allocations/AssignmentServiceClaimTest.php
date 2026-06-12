<?php

namespace App\Tests\Integration\Services\Allocations;

use App\Contracts\Servers\NodeRoutableGate;
use App\Exceptions\DisplayException;
use App\Exceptions\Servers\PortClaimConflictException;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Services\Allocations\AssignmentService;
use App\Tests\Integration\IntegrationTestCase;

// the admin "create allocation on a server" path inserts rows already bound to the
// server, so it is a fleet-wide bind site and must take the claim. tested single
// process here: sqlite (see phpunit.xml) has no real FOR UPDATE, so cross-process
// exclusion is only provable on canary mysql against a real database. what we prove
// here is the fence logic: a port owned on any node is refused, a free port binds,
// and a node with no routing peer is refused.
class AssignmentServiceClaimTest extends IntegrationTestCase
{
    public function test_binding_a_new_allocation_to_a_server_refuses_a_port_owned_on_another_node(): void
    {
        $server = $this->createServerModel();
        $node = $server->node;
        $otherNode = Node::factory()->create();

        // the requested port is already bound to another server on another node.
        Allocation::factory()->create(['node_id' => $otherNode->id, 'port' => 31000, 'server_id' => Server::factory()->create()->id]);

        try {
            $this->getService()->handle($node, ['allocation_ip' => '10.0.0.1', 'allocation_ports' => ['31000']], $server);
            $this->fail('expected a PortClaimConflictException');
        } catch (PortClaimConflictException) {
        }

        // nothing was inserted on the server's node for the contested port.
        $this->assertDatabaseMissing('allocations', ['node_id' => $node->id, 'port' => 31000]);
    }

    public function test_binding_a_new_allocation_to_a_server_on_a_free_port_succeeds(): void
    {
        $server = $this->createServerModel();
        $node = $server->node;

        $this->getService()->handle($node, ['allocation_ip' => '10.0.0.2', 'allocation_ports' => ['31100']], $server);

        $this->assertDatabaseHas('allocations', ['node_id' => $node->id, 'port' => 31100, 'server_id' => $server->id]);
    }

    public function test_binding_to_a_node_without_a_routing_peer_is_refused(): void
    {
        $server = $this->createServerModel();
        $node = $server->node;

        $this->swap(NodeRoutableGate::class, new class implements NodeRoutableGate
        {
            public function routable(int $nodeId): bool
            {
                return false;
            }
        });

        try {
            $this->getService()->handle($node, ['allocation_ip' => '10.0.0.3', 'allocation_ports' => ['31200']], $server);
            $this->fail('expected a DisplayException for the peerless node');
        } catch (PortClaimConflictException) {
            $this->fail('peerless refusal must not be a retryable claim conflict');
        } catch (DisplayException) {
        }

        $this->assertDatabaseMissing('allocations', ['node_id' => $node->id, 'port' => 31200]);
    }

    public function test_creating_bare_node_inventory_without_a_server_takes_no_claim(): void
    {
        $node = Node::factory()->create();

        // no server: this is node inventory creation, the claim path is skipped.
        $this->swap(NodeRoutableGate::class, new class implements NodeRoutableGate
        {
            public function routable(int $nodeId): bool
            {
                return false;
            }
        });

        $this->getService()->handle($node, ['allocation_ip' => '10.0.0.4', 'allocation_ports' => ['31300']], null);

        $this->assertDatabaseHas('allocations', ['node_id' => $node->id, 'port' => 31300, 'server_id' => null]);
    }

    private function getService(): AssignmentService
    {
        return $this->app->make(AssignmentService::class);
    }
}
