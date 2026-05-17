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

test('console boot script writes the offline marker lines on stopped state', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Offline);

    // showMarkerOnly=true (passed by stopped.blade.php) injects two
    // dim-ansi terminal.writeln calls in the boot @script block.
    // em-dashes render as the unicode escape — and quotes get
    // html-encoded inside the script block, so anchor on the
    // substring "marked as offline" which is unambiguous.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertSee('marked as offline', escape: false)
        ->assertSee('Hit start in the header to bring it back', escape: false);
});

test('console boot script omits marker writes when showMarkerOnly is false', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Running);

    // running state passes neither readOnly nor showMarkerOnly, so the
    // marker block doesn't render in the boot script.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertDontSee('marked as offline', escape: false);
});

test('console hides the command input on transient state (live but read-only)', function () {
    [$user, $server] = consoleReadOnlySeed(ContainerStatus::Starting);

    // transient passes readOnly=true (no marker mode) — input gated off
    // while wings still streams stats + console output live.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertDontSee('id="send-command"', escape: false)
        ->assertDontSee('marked as offline', escape: false);
});
