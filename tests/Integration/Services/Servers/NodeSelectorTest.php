<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\NodeSelector;
use App\Models\Node;
use App\Models\Server;
use App\Services\Servers\FirstAvailableNodeSelector;
use App\Tests\Integration\IntegrationTestCase;

class NodeSelectorTest extends IntegrationTestCase
{
    public function test_first_available_returns_first_viable_node_by_id(): void
    {
        $nodeA = Node::factory()->create(['memory' => 8192, 'disk' => 100000, 'cpu' => 100]);
        $nodeB = Node::factory()->create(['memory' => 8192, 'disk' => 100000, 'cpu' => 100]);
        $server = Server::factory()->make(['memory' => 1024, 'disk' => 10000, 'cpu' => 50]);

        $selector = new FirstAvailableNodeSelector();
        $picked = $selector->selectFor($server, [$nodeA->id, $nodeB->id]);

        $this->assertNotNull($picked);
        $this->assertSame($nodeA->id, $picked->id);
    }

    public function test_returns_null_when_no_node_viable(): void
    {
        $node = Node::factory()->create(['memory' => 1024, 'disk' => 1024, 'cpu' => 50]);
        $server = Server::factory()->make(['memory' => 99999, 'disk' => 99999, 'cpu' => 9999]);

        $selector = new FirstAvailableNodeSelector();
        $picked = $selector->selectFor($server, [$node->id]);

        $this->assertNull($picked);
    }

    public function test_score_returns_zero_for_viable_and_null_for_not(): void
    {
        $viable = Node::factory()->create(['memory' => 8192, 'disk' => 100000, 'cpu' => 100]);
        $tight = Node::factory()->create(['memory' => 256, 'disk' => 256, 'cpu' => 10]);
        $server = Server::factory()->make(['memory' => 1024, 'disk' => 10000, 'cpu' => 50]);

        $selector = new FirstAvailableNodeSelector();

        $viable->loadSum('servers', 'memory');
        $viable->loadSum('servers', 'disk');
        $viable->loadSum('servers', 'cpu');
        $tight->loadSum('servers', 'memory');
        $tight->loadSum('servers', 'disk');
        $tight->loadSum('servers', 'cpu');

        $this->assertSame(0.0, $selector->score($viable, $server));
        $this->assertNull($selector->score($tight, $server));
    }

    public function test_default_binding_implements_contract(): void
    {
        $this->assertInstanceOf(NodeSelector::class, new FirstAvailableNodeSelector());
    }

    public function test_maintenance_nodes_are_skipped(): void
    {
        $down = Node::factory()->create(['memory' => 8192, 'disk' => 100000, 'cpu' => 100, 'maintenance_mode' => true]);
        $up = Node::factory()->create(['memory' => 8192, 'disk' => 100000, 'cpu' => 100]);
        $server = Server::factory()->make(['memory' => 1024, 'disk' => 10000, 'cpu' => 50]);

        $selector = new FirstAvailableNodeSelector();
        $picked = $selector->selectFor($server, [$down->id, $up->id]);

        $this->assertNotNull($picked);
        $this->assertSame($up->id, $picked->id);
    }

    public function test_score_returns_null_for_maintenance_node(): void
    {
        $down = Node::factory()->create(['memory' => 8192, 'disk' => 100000, 'cpu' => 100, 'maintenance_mode' => true]);
        $server = Server::factory()->make(['memory' => 1024, 'disk' => 10000, 'cpu' => 50]);

        $selector = new FirstAvailableNodeSelector();

        $down->loadSum('servers', 'memory');
        $down->loadSum('servers', 'disk');
        $down->loadSum('servers', 'cpu');

        $this->assertNull($selector->score($down, $server));
    }
}
