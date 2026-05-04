<?php

namespace App\Tests\Integration\Services\Nodes;

use App\Models\Node;
use App\Services\Nodes\NodeHealthService;
use App\Tests\Integration\IntegrationTestCase;
use Carbon\Carbon;

class NodeHealthServiceTest extends IntegrationTestCase
{
    private NodeHealthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new NodeHealthService();
    }

    public function test_threshold_defaults_to_node_constant(): void
    {
        $this->assertSame(Node::HEALTH_THRESHOLD_SECONDS, $this->service->getThreshold());
    }

    public function test_threshold_can_be_overridden_via_constructor(): void
    {
        $service = new NodeHealthService(30);

        $this->assertSame(30, $service->getThreshold());
    }

    public function test_node_with_recent_last_seen_is_healthy(): void
    {
        $node = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(60)]);

        $this->assertTrue($this->service->isHealthy($node));
    }

    public function test_node_with_stale_last_seen_is_unhealthy(): void
    {
        $node = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(180)]);

        $this->assertFalse($this->service->isHealthy($node));
    }

    public function test_node_with_null_last_seen_is_unhealthy(): void
    {
        $node = Node::factory()->create(['last_seen' => null]);

        $this->assertFalse($this->service->isHealthy($node));
    }

    public function test_get_healthy_returns_only_recent_nodes(): void
    {
        $fresh = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(10)]);
        $stale = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(300)]);
        $never = Node::factory()->create(['last_seen' => null]);

        $healthy = $this->service->getHealthy()->pluck('id')->all();

        $this->assertContains($fresh->id, $healthy);
        $this->assertNotContains($stale->id, $healthy);
        $this->assertNotContains($never->id, $healthy);
    }

    public function test_get_unhealthy_includes_stale_and_never_seen(): void
    {
        $fresh = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(10)]);
        $stale = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(300)]);
        $never = Node::factory()->create(['last_seen' => null]);

        $unhealthy = $this->service->getUnhealthy()->pluck('id')->all();

        $this->assertNotContains($fresh->id, $unhealthy);
        $this->assertContains($stale->id, $unhealthy);
        $this->assertContains($never->id, $unhealthy);
    }

    public function test_has_healthy_node_reflects_current_state(): void
    {
        Node::factory()->create(['last_seen' => null]);
        $this->assertFalse($this->service->hasHealthyNode());

        Node::factory()->create(['last_seen' => Carbon::now()]);
        $this->assertTrue($this->service->hasHealthyNode());
    }

    public function test_threshold_override_changes_classification(): void
    {
        $node = Node::factory()->create(['last_seen' => Carbon::now()->subSeconds(60)]);

        $strict = new NodeHealthService(30);
        $loose = new NodeHealthService(300);

        $this->assertFalse($strict->isHealthy($node));
        $this->assertTrue($loose->isHealthy($node));
    }
}
