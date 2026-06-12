<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\NodeRoutableGate;
use App\Exceptions\DisplayException;
use App\Exceptions\Servers\PortClaimConflictException;
use App\Models\Allocation;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\ServerCreationService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery\MockInterface;

// the fleet-wide fence is tested single-process here: sqlite (the test database, see
// phpunit.xml) has no real FOR UPDATE and isolates each connection, so a true
// two-process concurrency test is not faithful. real cross-process FOR UPDATE
// exclusion is proven on canary mysql (phase 7), not sqlite. what we prove here is
// the fence LOGIC: a port already bound elsewhere makes the create throw and bind
// nothing, and a multi-port create binds every port under one claim.
class ServerCreationClaimTest extends IntegrationTestCase
{
    use WithFaker;

    protected MockInterface $daemonServerRepository;

    protected Egg $bungeecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bungeecord = Egg::query()
            ->where('author', 'panel@example.com')
            ->where('name', 'Bungeecord')
            ->firstOrFail();

        $this->daemonServerRepository = \Mockery::mock(DaemonServerRepository::class);
        $this->swap(DaemonServerRepository::class, $this->daemonServerRepository);
    }

    public function test_create_refuses_a_primary_port_bound_on_another_node(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create();
        $otherNode = Node::factory()->create();

        // the port the create wants is already bound to another server on another node.
        $bound = Allocation::factory()->create(['node_id' => $otherNode->id, 'port' => 25565, 'server_id' => Server::factory()->create()->id]);
        $free = Allocation::factory()->create(['node_id' => $node->id, 'port' => 25565, 'server_id' => null]);

        $this->daemonServerRepository->shouldReceive('setServer->create')->never();

        try {
            $this->getService()->handle($this->baseData($user, $node, $free->id));
            $this->fail('expected a PortClaimConflictException');
        } catch (PortClaimConflictException) {
        }

        // the free row stays unbound; nothing was claimed on conflict.
        $this->assertDatabaseHas('allocations', ['id' => $free->id, 'server_id' => null]);
        $this->assertDatabaseHas('allocations', ['id' => $bound->id, 'server_id' => $bound->server_id]);
    }

    public function test_create_refuses_an_additional_port_bound_on_another_node(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create();
        $otherNode = Node::factory()->create();

        $primary = Allocation::factory()->create(['node_id' => $node->id, 'port' => 26000, 'server_id' => null]);
        // the additional port is the conflicting one.
        Allocation::factory()->create(['node_id' => $otherNode->id, 'port' => 26001, 'server_id' => Server::factory()->create()->id]);
        $additional = Allocation::factory()->create(['node_id' => $node->id, 'port' => 26001, 'server_id' => null]);

        $this->daemonServerRepository->shouldReceive('setServer->create')->never();

        try {
            $this->getService()->handle(array_merge(
                $this->baseData($user, $node, $primary->id),
                ['allocation_additional' => [$additional->id]],
            ));
            $this->fail('expected a PortClaimConflictException');
        } catch (PortClaimConflictException) {
        }

        // neither the primary nor the additional free row is bound on conflict.
        $this->assertDatabaseHas('allocations', ['id' => $primary->id, 'server_id' => null]);
        $this->assertDatabaseHas('allocations', ['id' => $additional->id, 'server_id' => null]);
    }

    public function test_create_binds_the_full_port_set_under_one_claim(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create();

        $primary = Allocation::factory()->create(['node_id' => $node->id, 'port' => 27000, 'server_id' => null]);
        $a1 = Allocation::factory()->create(['node_id' => $node->id, 'port' => 27001, 'server_id' => null]);
        $a2 = Allocation::factory()->create(['node_id' => $node->id, 'port' => 27002, 'server_id' => null]);

        $this->daemonServerRepository->expects('setServer->create')->andReturnUndefined();

        $server = $this->getService()->handle(array_merge(
            $this->baseData($user, $node, $primary->id),
            ['allocation_additional' => [$a1->id, $a2->id]],
        ));

        // every port in the set is bound to the new server under the single claim.
        $this->assertDatabaseHas('allocations', ['id' => $primary->id, 'server_id' => $server->id]);
        $this->assertDatabaseHas('allocations', ['id' => $a1->id, 'server_id' => $server->id]);
        $this->assertDatabaseHas('allocations', ['id' => $a2->id, 'server_id' => $server->id]);
    }

    public function test_create_is_refused_on_a_node_without_a_routing_peer(): void
    {
        $user = User::factory()->create();
        $node = Node::factory()->create();
        $free = Allocation::factory()->create(['node_id' => $node->id, 'port' => 28000, 'server_id' => null]);

        // a node with no routing peer is not a placement target, even for a free port.
        $this->swap(NodeRoutableGate::class, new class implements NodeRoutableGate
        {
            public function routable(int $nodeId): bool
            {
                return false;
            }
        });

        $this->daemonServerRepository->shouldReceive('setServer->create')->never();

        try {
            $this->getService()->handle($this->baseData($user, $node, $free->id));
            $this->fail('expected a DisplayException for the peerless node');
        } catch (PortClaimConflictException) {
            $this->fail('peerless refusal must not be a retryable claim conflict');
        } catch (DisplayException) {
        }

        $this->assertDatabaseHas('allocations', ['id' => $free->id, 'server_id' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseData(User $user, Node $node, int $allocationId): array
    {
        return [
            'name' => $this->faker->name(),
            'description' => $this->faker->sentence(),
            'owner_id' => $user->id,
            'allocation_id' => $allocationId,
            'node_id' => $node->id,
            'memory' => 256,
            'swap' => 128,
            'disk' => 100,
            'io' => 500,
            'cpu' => 0,
            'startup' => 'java server2.jar',
            'image' => 'java:8',
            'egg_id' => $this->bungeecord->id,
            'environment' => [
                'BUNGEE_VERSION' => '123',
                'SERVER_JARFILE' => 'server2.jar',
            ],
        ];
    }

    private function getService(): ServerCreationService
    {
        return $this->app->make(ServerCreationService::class);
    }
}
