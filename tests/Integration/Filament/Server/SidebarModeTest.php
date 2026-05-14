<?php

use App\Enums\ServerState;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('full sidebar shows node-gated pages when server has a node', function () {
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    [$user, $server] = sidebarSeed(['egg_id' => $egg->id]);

    $response = $this->actingAs($user)->get("/server/{$server->uuid_short}/overview");
    $response->assertOk();
    $response->assertSee('Files', escape: false);
    $response->assertSee('Backups', escape: false);
    $response->assertSee('Activity', escape: false);
});

test('bare sidebar omits node-gated pages when server is in Nest state', function () {
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    [$user, $server] = sidebarSeed([
        'egg_id' => $egg->id,
        'status' => ServerState::Nest,
    ]);

    $response = $this->actingAs($user)->get("/server/{$server->uuid_short}/overview");
    $response->assertOk();
    $response->assertDontSee('>Files<', escape: false);
    $response->assertDontSee('>Backups<', escape: false);
    $response->assertSee('Activity', escape: false);
});

function sidebarSeed(array $serverOverrides = []): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $server = Server::factory()->for($user, 'user')->create($serverOverrides);

    return [$user, $server];
}
