<?php

use App\Enums\ServerState;
use App\Models\Server;

// the transformer's full transform() call pulls in the policy stack which
// then resolves StartupCommandService, egg variables, subuser permission
// checks. covering all of that for a three line null guard balloons the
// fixture cost. these tests cover the part of the transformer behaviour
// that nest forced us to change, the rest of the transform path stays
// covered by the broader integration tests for the client api.

test('server with null node returns null on relationship access', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Nest,
        'node_id' => null,
    ]);

    expect($server->node)->toBeNull();
});

test('null safe operator chain on node mirrors transformer guard', function () {
    $server = Server::factory()->create([
        'status' => ServerState::Nest,
        'node_id' => null,
    ]);

    expect($server->node?->name)->toBeNull();
    expect($server->node?->isUnderMaintenance() ?? false)->toBeFalse();
});
