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

test('installing status renders the honey banner with progress band', function () {
    [$user, $server] = installingStateSeed(ServerState::Installing);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee(trans('server/overview.installing.installing.title'), escape: false)
        ->assertSee('overview-banner--installing', escape: false)
        ->assertSee('overview-progress-band--honey', escape: false);
});

test('install_failed flips to the suspended variant with retry hint', function () {
    [$user, $server] = installingStateSeed(ServerState::InstallFailed);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee(trans('server/overview.installing.install_failed.title'), escape: false)
        ->assertSee('overview-banner--suspended', escape: false)
        ->assertDontSee('overview-progress-band', escape: false);
});

test('reinstall_failed uses its own copy', function () {
    [$user, $server] = installingStateSeed(ServerState::ReinstallFailed);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee(trans('server/overview.installing.reinstall_failed.title'), escape: false);
});

test('installing grid is 4 columns with Egg / Version / World / Disk', function () {
    [$user, $server] = installingStateSeed(ServerState::Installing);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('md:grid-cols-4', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Egg</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Version</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">World</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Disk</p>', escape: false)
        ->assertDontSee('<p class="overview-stat-card__label">CPU load</p>', escape: false)
        ->assertDontSee('<p class="overview-stat-card__label">Memory</p>', escape: false);
});

test('installing Version card shows placeholder when egg has no version_var tag', function () {
    // overwrite the seed's egg with one that has no version_var: tag.
    // Egg::versionVar returns null → Server::version returns null → the
    // partial renders the placeholder dash inside the Version card.
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->state(['tags' => ['game:minecraft']])->create(['name' => 'Forge']);
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => ServerState::Installing,
    ]);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('<p class="overview-stat-card__label">Version</p>', escape: false)
        ->assertSee('overview-stat-empty-bar', escape: false)
        ->assertSee('overview-stat-empty-caption', escape: false);
});
