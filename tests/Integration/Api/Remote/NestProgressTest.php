<?php

namespace App\Tests\Integration\Api\Remote;

use App\Enums\ServerState;
use App\Models\Node;
use App\Services\Servers\RetrieveProgressCache;
use App\Tests\Integration\IntegrationTestCase;

class NestProgressTest extends IntegrationTestCase
{
    public function test_owning_node_can_post_downloading_progress(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Retrieving]);
        $node = Node::find($server->node_id);

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id.$node->daemon_token")
            ->postJson("/api/remote/servers/{$server->uuid}/nest-progress", [
                'step' => 'downloading',
                'bytes' => 1_000_000,
                'total_bytes' => 4_000_000,
            ])
            ->assertNoContent();

        $payload = app(RetrieveProgressCache::class)->get($server);
        $this->assertSame('downloading', $payload['step']);
        $this->assertSame(1_000_000, $payload['bytes']);
        $this->assertSame(4_000_000, $payload['total_bytes']);
        $this->assertIsInt($payload['streaming_started_at']);
    }

    public function test_owning_node_can_post_extracting_progress(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Retrieving]);
        $node = Node::find($server->node_id);

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id.$node->daemon_token")
            ->postJson("/api/remote/servers/{$server->uuid}/nest-progress", [
                'step' => 'extracting',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertNoContent();

        $this->assertSame('extracting', app(RetrieveProgressCache::class)->get($server)['step']);
    }

    public function test_unrelated_node_cannot_post_progress(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Retrieving]);
        $other = Node::factory()->create();

        $this->withHeader('Authorization', "Bearer $other->daemon_token_id.$other->daemon_token")
            ->postJson("/api/remote/servers/{$server->uuid}/nest-progress", [
                'step' => 'downloading',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertForbidden();

        $this->assertNull(app(RetrieveProgressCache::class)->get($server));
    }

    public function test_owning_node_can_post_starting_progress(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Retrieving]);
        $node = Node::find($server->node_id);

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id.$node->daemon_token")
            ->postJson("/api/remote/servers/{$server->uuid}/nest-progress", [
                'step' => 'starting',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertNoContent();

        $this->assertSame('starting', app(RetrieveProgressCache::class)->get($server)['step']);
    }

    public function test_invalid_step_is_rejected(): void
    {
        $server = $this->createServerModel(['status' => ServerState::Retrieving]);
        $node = Node::find($server->node_id);

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id.$node->daemon_token")
            ->postJson("/api/remote/servers/{$server->uuid}/nest-progress", [
                'step' => 'frobnicating',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertStatus(422);

        $this->assertNull(app(RetrieveProgressCache::class)->get($server));
    }
}
