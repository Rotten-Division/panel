<?php

use App\Enums\ContainerStatus;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Services\Servers\TransferProgressCache;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function transferringStateSeed(?array $progress = null): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);
    $oldNode = Node::factory()->create(['name' => 'uk-primary']);
    $newNode = Node::factory()->create(['name' => 'eu-amsterdam']);
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'node_id' => $oldNode->id,
        'status' => null,
    ]);
    ServerTransfer::factory()->create([
        'server_id' => $server->id,
        'old_node' => $oldNode->id,
        'new_node' => $newNode->id,
    ]);
    cache()->put("servers.{$server->uuid}.status", ContainerStatus::Offline, now()->addMinute());

    if ($progress !== null) {
        app(TransferProgressCache::class)->put($server, $progress);
    }

    return [$user, $server];
}

test('dispatcher routes an in-flight transfer to the transferring partial', function () {
    [$user, $server] = transferringStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Moving to a new home')
        ->assertSee('uk-primary → eu-amsterdam')
        ->assertSee('overview-banner--transient', escape: false)
        ->assertSee('overview-transfer-detail', escape: false);
});

test('transferring partial wires a livewire poll so progress can refresh', function () {
    [$user, $server] = transferringStateSeed();

    // wings posts transfer-progress server-side (phase 2, approach B); the
    // websocket bridge never fires for byte updates, so the page must poll
    // to re-read $server->transferProgress between emissions. without this
    // wire:poll the bar would freeze at the initial render.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('wire:poll.1s="refreshLiveData"', escape: false);
});

test('transfer detail shows the waiting copy when no progress is cached yet', function () {
    [$user, $server] = transferringStateSeed();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Waiting on wings');
});

test('transfer detail renders the step label and byte progress when cached', function () {
    [$user, $server] = transferringStateSeed([
        'step' => 'uploading',
        'bytes' => 50 * 1024 * 1024,
        'total_bytes' => 100 * 1024 * 1024,
    ]);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Uploading to destination')
        ->assertSee('50.0 MiB')
        ->assertSee('100.0 MiB');
});

test('transfer detail renders an indeterminate marker for non-uploading steps', function () {
    [$user, $server] = transferringStateSeed([
        'step' => 'archiving',
        'bytes' => 0,
        'total_bytes' => 0,
    ]);

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Archiving server files')
        ->assertSee('Indeterminate');
});

test('transferring branch takes precedence over the family A status switch', function () {
    [$user, $server] = transferringStateSeed();

    // the transfer is present, so even though status is null and container
    // status is Offline (which would otherwise hit the stopped partial), the
    // dispatcher must route through the transferring branch first.
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Moving to a new home')
        ->assertDontSee(trans('server/overview.stopped.title'));
});
