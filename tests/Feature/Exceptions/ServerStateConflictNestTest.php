<?php

use App\Enums\ServerState;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Models\Server;

test('exception message for nest state mentions roosting', function () {
    $server = Server::factory()->make(['status' => ServerState::Nest, 'node_id' => null]);
    $exception = new ServerStateConflictException($server);

    expect($exception->getMessage())->toContain('roosting');
});

test('exception message for hydrating state mentions waking', function () {
    $server = Server::factory()->make(['status' => ServerState::Hydrating]);
    $exception = new ServerStateConflictException($server);

    expect($exception->getMessage())->toContain('waking');
});

test('exception message for capturing state mentions moving to cold storage', function () {
    $server = Server::factory()->make(['status' => ServerState::Capturing]);
    $exception = new ServerStateConflictException($server);

    expect($exception->getMessage())->toContain('moved to cold storage');
});
