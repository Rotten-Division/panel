<?php

use App\Enums\ServerState;
use App\Models\Server;
use App\Models\User;

test('client api refuses stashed server with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'node_id' => null,
        'status' => ServerState::Stashed,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertStatus(409);
});

test('client api permits server.view on retrieving', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Retrieving,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertOk();
});

test('client api permits server.resources on retrieving', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Retrieving,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}/resources")
        ->assertOk();
});

test('client api refuses other paths on retrieving with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Retrieving,
    ]);

    $this->actingAs($user)
        ->post("/api/client/servers/{$server->uuid}/power", ['signal' => 'start'])
        ->assertStatus(409);
});

test('client api permits server.view on stashing so the front end can poll', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Stashing,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertOk();
});

test('client api refuses power and file actions on stashing with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Stashing,
    ]);

    $this->actingAs($user)
        ->post("/api/client/servers/{$server->uuid}/power", ['signal' => 'start'])
        ->assertStatus(409);
});
