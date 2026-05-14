<?php

use App\Enums\ContainerStatus;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function transientStateSeed(ContainerStatus $status): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => null,
    ]);
    cache()->put("servers.{$server->uuid}.status", $status, now()->addMinute());

    return [$user, $server];
}

test('starting status renders the transient banner with progress band', function () {
    [$user, $server] = transientStateSeed(ContainerStatus::Starting);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Starting up', escape: false)
        ->assertSee('overview-banner--transient', escape: false)
        ->assertSee('overview-banner__progress', escape: false);
});

test('stopping status flips the banner title and subtitle', function () {
    [$user, $server] = transientStateSeed(ContainerStatus::Stopping);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Stopping', escape: false)
        ->assertSee('Saving state', escape: false);
});

test('restarting status uses the dedicated copy', function () {
    [$user, $server] = transientStateSeed(ContainerStatus::Restarting);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Restarting', escape: false)
        ->assertSee('cycling the container', escape: false);
});
