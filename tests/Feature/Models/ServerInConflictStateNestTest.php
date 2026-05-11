<?php

use App\Enums\ServerState;
use App\Models\Server;

test('nest server is in conflict state', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Nest,
        'node_id' => null,
    ]);

    expect($server->isInConflictState())->toBeTrue();
});

test('hydrating server is in conflict state', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Hydrating,
    ]);

    expect($server->isInConflictState())->toBeTrue();
});

test('capturing server is in conflict state so user actions cannot race the in flight eviction', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Capturing,
    ]);

    expect($server->isInConflictState())->toBeTrue();
});

test('isInConflictState does not throw on null node', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Nest,
        'node_id' => null,
    ]);

    // null node access used to throw, the null safe chain in isInConflictState
    // means the predicate evaluates without dereferencing the missing relation.
    expect(fn () => $server->isInConflictState())->not->toThrow(Throwable::class);
});
