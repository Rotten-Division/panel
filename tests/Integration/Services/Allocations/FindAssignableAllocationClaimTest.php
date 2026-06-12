<?php

namespace App\Tests\Integration\Services\Allocations;

use App\Exceptions\Servers\PortClaimConflictException;
use App\Models\Allocation;
use App\Services\Allocations\FindAssignableAllocationService;
use App\Tests\Integration\IntegrationTestCase;

// the client API and the Filament Network tab both auto-assign through this one
// service, so this single service test covers both entry points.
//
// the fence is tested single-process: sqlite (see phpunit.xml) has no real FOR
// UPDATE and isolates each connection, so a true two-process concurrency test is not
// faithful. real cross-process FOR UPDATE exclusion is proven on canary mysql (phase
// 7), not sqlite. here we prove the disposition LOGIC: a port bound elsewhere is
// refused and bound nothing.
class FindAssignableAllocationClaimTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.client_features.allocations.enabled', true);
    }

    public function test_auto_assign_refuses_a_port_bound_on_another_node(): void
    {
        $server = $this->createServerModel();
        $other = $this->createServerModel();

        config()->set('panel.client_features.allocations.range_start', 30000);
        config()->set('panel.client_features.allocations.range_end', 30000);

        // another node holds 30000 bound to $other; the server's node has a free 30000.
        Allocation::factory()->create(['node_id' => $other->node_id, 'port' => 30000, 'server_id' => $other->id]);
        $free = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'ip' => $server->allocation->ip,
            'port' => 30000,
            'server_id' => null,
        ]);

        $this->expectException(PortClaimConflictException::class);

        try {
            $this->getService()->handle($server);
        } finally {
            $this->assertDatabaseHas('allocations', ['id' => $free->id, 'server_id' => null]);
        }
    }

    public function test_auto_assign_binds_a_free_port_through_the_claim(): void
    {
        $server = $this->createServerModel();

        config()->set('panel.client_features.allocations.range_start', 31000);
        config()->set('panel.client_features.allocations.range_end', 31000);

        $free = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'ip' => $server->allocation->ip,
            'port' => 31000,
            'server_id' => null,
        ]);

        $response = $this->getService()->handle($server);

        $this->assertSame($free->id, $response->id);
        $this->assertSame($server->id, $response->server_id);
    }

    private function getService(): FindAssignableAllocationService
    {
        return $this->app->make(FindAssignableAllocationService::class);
    }
}
