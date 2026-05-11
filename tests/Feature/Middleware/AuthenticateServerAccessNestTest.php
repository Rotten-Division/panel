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

test('client api permits server.view on hydrating', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Hydrating,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertOk();
});

test('client api permits server.resources on hydrating', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Hydrating,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}/resources")
        ->assertOk();
});

test('client api refuses other paths on hydrating with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Hydrating,
    ]);

    $this->actingAs($user)
        ->post("/api/client/servers/{$server->uuid}/power", ['signal' => 'start'])
        ->assertStatus(409);
});

test('client api permits server.view on capturing so the front end can poll', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Capturing,
    ]);

    $this->actingAs($user)
        ->get("/api/client/servers/{$server->uuid}")
        ->assertOk();
});

test('client api refuses power and file actions on capturing with 409', function () {
    $user = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $user->id,
        'status' => ServerState::Capturing,
    ]);

    $this->actingAs($user)
        ->post("/api/client/servers/{$server->uuid}/power", ['signal' => 'start'])
        ->assertStatus(409);
});
