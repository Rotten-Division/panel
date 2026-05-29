<?php

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Filament\Server\Pages\Overview;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;
use Filament\Facades\Filament;
use Livewire\Livewire;

uses(IntegrationTestCase::class);

// array cache persists across tests in one process while DatabaseTruncation
// resets server IDs to 1, so seeded `servers.1.*` keys leak into later tests.
// flush per case so each test starts with an empty wings stats cache.
beforeEach(fn () => cache()->flush());

function seedServerForLiveData(): Server
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    auth()->login($user);
    $egg = Egg::factory()->withGameTag('minecraft')->create(['name' => 'Forge']);

    return Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => null,
    ]);
}

test('refreshLiveData reads disk and uptime from the cache time series', function () {
    $server = seedServerForLiveData();
    Filament::setTenant($server);

    cache()->put("servers.$server->id.disk_bytes", [
        100 => 50_000_000,
        200 => 150_000_000,
        300 => 250_000_000,
    ], now()->addMinute());
    cache()->put("servers.$server->id.uptime", [
        100 => 1_000,
        200 => 60_000,
        300 => 120_000,
    ], now()->addMinute());

    $page = new Overview();
    $page->status = ContainerStatus::Running;
    $page->refreshLiveData();

    expect($page->diskUsedBytes)->toBe(250_000_000);
    expect($page->uptimeMs)->toBe(120_000);
});

test('refreshLiveData zeroes uptime when status is Offline or Stopping', function () {
    $server = seedServerForLiveData();
    Filament::setTenant($server);

    // wings holds the pre-stop uptime in cache for a tick after stop; the
    // page guards against showing stale running-uptime once the container
    // transitions out of running.
    cache()->put("servers.$server->id.uptime", [
        100 => 3_600_000,
    ], now()->addMinute());

    $page = new Overview();
    $page->status = ContainerStatus::Offline;
    $page->refreshLiveData();
    expect($page->uptimeMs)->toBe(0);

    $page = new Overview();
    $page->status = ContainerStatus::Stopping;
    $page->refreshLiveData();
    expect($page->uptimeMs)->toBe(0);

    // sanity check the running case still reads the cached value
    $page = new Overview();
    $page->status = ContainerStatus::Running;
    $page->refreshLiveData();
    expect($page->uptimeMs)->toBe(3_600_000);
});

test('refreshLiveData falls back to zero when the cache is empty', function () {
    $server = seedServerForLiveData();
    Filament::setTenant($server);

    $page = new Overview();
    $page->status = ContainerStatus::Running;
    $page->refreshLiveData();

    expect($page->diskUsedBytes)->toBe(0);
    expect($page->uptimeMs)->toBe(0);
});

test('refreshLiveData forces a full reload when the server leaves the stash family', function () {
    $server = seedServerForLiveData();
    $server->update(['status' => ServerState::Retrieving]);
    Filament::setTenant($server);

    $page = Livewire::test(Overview::class);
    // landing on a retrieving server records family-b membership, no redirect
    expect($page->get('wasStashFamily'))->toBeTrue();
    $page->call('refreshLiveData')->assertNoRedirect();

    // retrieve completes and status flips to operational. the operational body
    // needs a full page load to boot the wings socket, so the poll redirects
    // instead of letting livewire morph in a socketless stopped view.
    $server->update(['status' => null]);
    $page->call('refreshLiveData')
        ->assertRedirect(Overview::getUrl(panel: 'server', tenant: $server));
});

test('refreshLiveData does not redirect while the server stays operational', function () {
    $server = seedServerForLiveData();
    Filament::setTenant($server);

    Livewire::test(Overview::class)
        ->assertSet('wasStashFamily', false)
        ->call('refreshLiveData')
        ->assertNoRedirect();
});

test('diskBarTone honours 60 and 85 thresholds against the server cap', function () {
    $server = seedServerForLiveData();
    $server->update(['disk' => 1024]); // 1 GiB cap in MiB
    Filament::setTenant($server);

    $page = new Overview();
    $page->status = ContainerStatus::Running;

    $page->diskUsedBytes = 100_000_000; // ~9.5%
    expect($page->diskBarTone())->toBe('success');

    $page->diskUsedBytes = 700_000_000; // ~65%
    expect($page->diskBarTone())->toBe('warning');

    $page->diskUsedBytes = 950_000_000; // ~88%
    expect($page->diskBarTone())->toBe('danger');
});
