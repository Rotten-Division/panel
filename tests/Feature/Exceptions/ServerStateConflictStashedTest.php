<?php

use App\Enums\ServerState;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Models\Server;

test('exception message for stashed state mentions cold storage', function () {
    $server = Server::factory()->make(['status' => ServerState::Stashed, 'node_id' => null]);
    $exception = new ServerStateConflictException($server);

    expect($exception->getMessage())->toContain('in cold storage');
});

test('exception message for retrieving state mentions retrieval', function () {
    $server = Server::factory()->make(['status' => ServerState::Retrieving]);
    $exception = new ServerStateConflictException($server);

    expect($exception->getMessage())->toContain('being retrieved');
});

test('exception message for stashing state mentions moving to cold storage', function () {
    $server = Server::factory()->make(['status' => ServerState::Stashing]);
    $exception = new ServerStateConflictException($server);

    expect($exception->getMessage())->toContain('moved to cold storage');
});
