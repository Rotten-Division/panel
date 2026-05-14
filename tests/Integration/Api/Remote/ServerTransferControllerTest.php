<?php

namespace App\Tests\Integration\Api\Remote;

use App\Models\Allocation;
use App\Models\Node;
use App\Models\ServerTransfer;
use App\Services\Servers\TransferProgressCache;
use App\Tests\Integration\IntegrationTestCase;

class ServerTransferControllerTest extends IntegrationTestCase
{
    protected ServerTransfer $transfer;

    protected function setup(): void
    {
        parent::setUp();

        $server = $this->createServerModel();

        $new = Node::factory()
            ->has(Allocation::factory())
            ->create();

        $this->transfer = ServerTransfer::factory()->for($server)->create([
            'old_allocation' => $server->allocation_id,
            'new_allocation' => $new->allocations->first()->id,
            'new_node' => $new->id,
            'old_node' => $server->node_id,
        ]);
    }

    public function test_success_status_update_can_be_sent_from_new_node(): void
    {
        $server = $this->transfer->server;
        $newNode = $this->transfer->newNode;

        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertNoContent();

        $this->assertTrue($this->transfer->refresh()->successful);
    }

    public function test_failure_status_update_can_be_sent_from_old_node(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/failure")
            ->assertNoContent();

        $this->assertFalse($this->transfer->refresh()->successful);
    }

    public function test_failure_status_update_can_be_sent_from_new_node(): void
    {
        $server = $this->transfer->server;
        $newNode = $this->transfer->newNode;

        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/failure")
            ->assertNoContent();

        $this->assertFalse($this->transfer->refresh()->successful);
    }

    public function test_success_status_update_cannot_be_sent_from_old_node(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/{$server->uuid}/transfer/success")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'HttpForbiddenException')
            ->assertJsonPath('errors.0.detail', 'Requesting node does not have permission to access this server.');

        $this->assertNull($this->transfer->refresh()->successful);
    }

    public function test_success_status_update_cannot_be_sent_from_unauthorized_node(): void
    {
        $server = $this->transfer->server;
        $node = Node::factory()->create();

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer/success")
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'HttpForbiddenException')
            ->assertJsonPath('errors.0.detail', 'Requesting node does not have permission to access this server.');

        $this->assertNull($this->transfer->refresh()->successful);
    }

    public function test_failure_status_update_cannot_be_sent_from_unauthorized_node(): void
    {
        $server = $this->transfer->server;
        $node = Node::factory()->create();

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer/failure")->assertForbidden()
            ->assertJsonPath('errors.0.code', 'HttpForbiddenException')
            ->assertJsonPath('errors.0.detail', 'Requesting node does not have permission to access this server.');

        $this->assertNull($this->transfer->refresh()->successful);
    }

    public function test_progress_update_can_be_sent_from_old_node(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer-progress", [
                'step' => 'uploading',
                'bytes' => 1_000_000,
                'total_bytes' => 4_000_000,
            ])
            ->assertNoContent();

        $payload = app(TransferProgressCache::class)->get($server);
        $this->assertSame('uploading', $payload['step']);
        $this->assertSame(1_000_000, $payload['bytes']);
        $this->assertSame(4_000_000, $payload['total_bytes']);
    }

    public function test_progress_update_can_be_sent_from_new_node(): void
    {
        $server = $this->transfer->server;
        $newNode = $this->transfer->newNode;

        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer-progress", [
                'step' => 'extracting',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertNoContent();

        $this->assertSame('extracting', app(TransferProgressCache::class)->get($server)['step']);
    }

    public function test_progress_update_rejects_unknown_step(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer-progress", [
                'step' => 'frobnicating',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertStatus(422);

        $this->assertNull(app(TransferProgressCache::class)->get($server));
    }

    public function test_progress_update_rejects_unauthorized_node(): void
    {
        $server = $this->transfer->server;
        $node = Node::factory()->create();

        $this->withHeader('Authorization', "Bearer $node->daemon_token_id." . $node->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer-progress", [
                'step' => 'uploading',
                'bytes' => 0,
                'total_bytes' => 0,
            ])
            ->assertForbidden();

        $this->assertNull(app(TransferProgressCache::class)->get($server));
    }

    public function test_progress_cache_is_cleared_on_transfer_success(): void
    {
        $server = $this->transfer->server;
        $newNode = $this->transfer->newNode;
        app(TransferProgressCache::class)->put($server, [
            'step' => 'cleanup', 'bytes' => 0, 'total_bytes' => 0,
        ]);

        $this->withHeader('Authorization', "Bearer $newNode->daemon_token_id." . $newNode->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer/success")
            ->assertNoContent();

        $this->assertNull(app(TransferProgressCache::class)->get($server->fresh()));
    }

    public function test_progress_cache_is_cleared_on_transfer_failure(): void
    {
        $server = $this->transfer->server;
        $oldNode = $this->transfer->oldNode;
        app(TransferProgressCache::class)->put($server, [
            'step' => 'uploading', 'bytes' => 1, 'total_bytes' => 2,
        ]);

        $this->withHeader('Authorization', "Bearer $oldNode->daemon_token_id." . $oldNode->daemon_token)
            ->postJson("/api/remote/servers/$server->uuid/transfer/failure")
            ->assertNoContent();

        $this->assertNull(app(TransferProgressCache::class)->get($server));
    }
}
