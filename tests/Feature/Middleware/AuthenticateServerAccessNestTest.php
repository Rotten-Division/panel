<?php

use App\Enums\ServerState;
use App\Models\Server;
use App\Models\User;

test('client api refuses nest server with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'node_id' => null,
        'status' => ServerState::Nest,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertStatus(409);
});

test('client api refuses hydrating server with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Hydrating,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertStatus(409);
});
