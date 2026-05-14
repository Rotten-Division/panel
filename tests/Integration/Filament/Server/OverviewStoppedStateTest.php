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

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Server stopped', escape: false)
        ->assertSee('Hit start to bring it online', escape: false)
        ->assertSee('overview-banner--default', escape: false);
});

test('stopped state renders the players, uptime, and disk cards', function () {
    [$user, $server] = stoppedStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Players', escape: false)
        ->assertSee('Uptime', escape: false)
        ->assertSee('Disk', escape: false)
        ->assertDontSee('World size', escape: false)
        ->assertSee('overview-stat-card--muted', escape: false);
});
