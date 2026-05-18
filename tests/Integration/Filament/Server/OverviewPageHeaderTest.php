<?php

use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

test('overview header renders the eyebrow from server accessors', function () {
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    [$user, $server] = seedAccountAndServer([
        'egg_id' => $egg->id,
    ]);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        // eyebrow is flavour + version only — game was dropped in the
        // page-head redesign since it was a no-op on a minecraft-only
        // panel. only the flavour label remains.
        ->assertSee('Forge', escape: false)
        ->assertDontSee('minecraft', escape: false);
});

test('overview header eyebrow shows the egg name regardless of game tag', function () {
    $egg = Egg::factory()->withGameTag('bedrock', 'BEDROCK_VERSION')->create(['name' => 'Vanilla Bedrock']);
    [$user, $server] = seedAccountAndServer([
        'egg_id' => $egg->id,
    ]);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Vanilla Bedrock', escape: false);
});

function seedAccountAndServer(array $serverOverrides = []): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $server = Server::factory()->for($user, 'user')->create($serverOverrides);

    return [$user, $server];
}
