<?php

use App\Enums\ContainerStatus;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function consoleReadOnlySeed(ContainerStatus $status): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create();
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => null,
    ]);
    cache()->put("servers.{$server->uuid}.status", $status, now()->addMinute());

    return [$user, $server];
}

test('console hides the command input row on stopped state', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Offline);

    // Stopped partial passes readOnly=true → the @if-guarded input row is
    // suppressed even though authorizeSendCommand() returns true.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertDontSee('id="send-command"', escape: false);
});

test('console shows the command input row on running state', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Running);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('id="send-command"', escape: false);
});

test('console never writes the offline marker copy', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Offline);

    // marker mode was removed — the stopped console should be plain.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertDontSee('marked as offline', escape: false);
});

test('console hides the command input on transient state (live but read-only)', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Starting);

    // transient passes readOnly=true, input gated off while wings still
    // streams stats + console output live.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertDontSee('id="send-command"', escape: false);
});
