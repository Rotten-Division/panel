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
        ->assertSee(trans('server/overview.transient.starting.title'), escape: false)
        ->assertSee('overview-banner--transient', escape: false)
        ->assertSee('overview-progress-band', escape: false);
});

test('stopping status flips the banner title and subtitle', function () {
    [$user, $server] = transientStateSeed(ContainerStatus::Stopping);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee(trans('server/overview.transient.stopping.title'), escape: false)
        ->assertSee('Saving the game', escape: false);
});

test('restarting status uses the dedicated copy', function () {
    [$user, $server] = transientStateSeed(ContainerStatus::Restarting);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee(trans('server/overview.transient.restarting.title'), escape: false)
        ->assertSee('cycling the container', escape: false);
});

test('transient grid is 3 columns and drops the resource-card duplicates', function () {
    [$user, $server] = transientStateSeed(ContainerStatus::Starting);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('md:grid-cols-3', escape: false)
        ->assertDontSee('<p class="overview-stat-card__label">CPU load</p>', escape: false)
        ->assertDontSee('<p class="overview-stat-card__label">Memory</p>', escape: false)
        ->assertDontSee('<p class="overview-stat-card__label">World size</p>', escape: false);
});
