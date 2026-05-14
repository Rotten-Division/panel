<?php

use App\Enums\ServerState;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function installingStateSeed(ServerState $status): array
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

test('installing status renders the honey banner with egg name', function () {
    [$user, $server] = installingStateSeed(ServerState::Installing);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Setting up your server', escape: false)
        ->assertSee('Installing Forge', escape: false)
        ->assertSee('overview-banner--installing', escape: false)
        ->assertSee('overview-banner__progress', escape: false);
});

test('install_failed flips to the suspended variant with retry hint', function () {
    [$user, $server] = installingStateSeed(ServerState::InstallFailed);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Install failed', escape: false)
        ->assertSee('overview-banner--suspended', escape: false)
        ->assertDontSee('overview-banner__progress', escape: false);
});

test('reinstall_failed uses its own copy', function () {
    [$user, $server] = installingStateSeed(ServerState::ReinstallFailed);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Reinstall failed', escape: false);
});
