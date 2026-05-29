<?php

use App\Enums\ServerState;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function conflictRedirectSeed(?ServerState $status): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => $status,
    ]);

    return [$user, $server];
}

test('a stashed server redirects a non-overview page to the overview', function () {
    [$user, $server] = conflictRedirectSeed(ServerState::Stashed);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/settings")
        ->assertRedirect("/server/{$server->uuid_short}/overview");
});

test('the overview itself is not redirected for a stashed server', function () {
    [$user, $server] = conflictRedirectSeed(ServerState::Stashed);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk();
});

test('an operational server is not redirected away from its pages', function () {
    [$user, $server] = conflictRedirectSeed(null);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/settings")
        ->assertOk();
});
