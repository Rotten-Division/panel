<?php

use App\Enums\ContainerStatus;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function stoppedStateSeed(): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => null,
    ]);
    cache()->put("servers.{$server->uuid}.status", ContainerStatus::Offline, now()->addMinute());

    return [$user, $server];
}

test('stopped state shows the default banner', function () {
    [$user, $server] = stoppedStateSeed();

    // title contains an apostrophe — escape:true (default) html-encodes the
    // search string the same way blade does, so the match works against the
    // rendered &#039;. class names are static html so escape:false stays.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee(trans('server/overview.stopped.title'))
        ->assertSee('Hit start in the header to bring it back', escape: false)
        ->assertSee('overview-banner--default', escape: false);
});

test('stopped state renders the 5-card grid with offline placeholders', function () {
    [$user, $server] = stoppedStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('lg:grid-cols-5', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Players</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Uptime</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">CPU load</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Memory</p>', escape: false)
        ->assertSee('<p class="overview-stat-card__label">Disk</p>', escape: false)
        ->assertDontSee('<p class="overview-stat-card__label">World size</p>', escape: false)
        ->assertSee('overview-stat-card--muted', escape: false);
});

test('stopped state renders the spark row below the console', function () {
    [$user, $server] = stoppedStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('overview-spark overview-spark--hearth overview-spark--muted', escape: false);
});
