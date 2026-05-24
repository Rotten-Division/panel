<?php

use App\Enums\ServerState;
use App\Models\Server;

test('stashed server is in conflict state', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Stashed,
        'node_id' => null,
    ]);

    expect($server->isInConflictState())->toBeTrue();
});

test('retrieving server is in conflict state', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Retrieving,
    ]);

    expect($server->isInConflictState())->toBeTrue();
});

test('stashing server is in conflict state so user actions cannot race the in flight capture', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Stashing,
    ]);

    expect($server->isInConflictState())->toBeTrue();
});

test('isInConflictState does not throw on null node', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Stashed,
        'node_id' => null,
    ]);

    expect(fn () => $server->isInConflictState())->not->toThrow(Throwable::class);
});
