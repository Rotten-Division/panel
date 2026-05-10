<?php

use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Models\User;

test('owner can use subuser permission abilities on nest server with null node', function () {
    $owner = User::factory()->create();
    $server = Server::factory()->create([
        'owner_id' => $owner->id,
        'node_id' => null,
        'status' => ServerState::Nest,
    ]);

    expect($owner->can(SubuserPermission::FileRead, $server))->toBeTrue();
});

test('non owner non subuser stranger cannot use subuser permissions', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $server = Server::factory()->create([
        'owner_id' => $owner->id,
        'node_id' => null,
        'status' => ServerState::Nest,
    ]);

    expect($stranger->can(SubuserPermission::FileRead, $server))->toBeFalse();
});

test('policy does not throw on null node access', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $server = Server::factory()->create([
        'owner_id' => $owner->id,
        'node_id' => null,
        'status' => ServerState::Nest,
    ]);

    expect(fn () => $stranger->can(SubuserPermission::FileRead, $server))->not->toThrow(Throwable::class);
});
