<?php

use App\Enums\ContainerStatus;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function runningStateSeed(array $serverOverrides = []): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    $server = Server::factory()->for($user, 'user')->create(array_merge([
        'egg_id' => $egg->id,
        'status' => null,
    ], $serverOverrides));
    // prime the wings status cache so receivedConsoleUpdate doesn't need
    // a real websocket message.
    cache()->put("servers.{$server->uuid}.status", ContainerStatus::Running, now()->addMinute());

    return [$user, $server];
}

test('running state renders the three card stat grid', function () {
    [$user, $server] = runningStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Players', escape: false)
        ->assertSee('Uptime', escape: false)
        ->assertSee('Disk', escape: false);
});

test('players card shows the placeholder when no PlayerCountProvider data', function () {
    [$user, $server] = runningStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('overview-stat-card__placeholder', escape: false);
});

test('uptime card shows placeholder when no stats cached', function () {
    [$user, $server] = runningStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('overview-stat-card__placeholder', escape: false);
});
