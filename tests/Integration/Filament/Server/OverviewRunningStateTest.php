<?php

use App\Enums\ContainerStatus;
use App\Filament\Server\Widgets\ServerCpuChart;
use App\Models\Egg;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;
use Filament\Facades\Filament;
use Livewire\Livewire;

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

test('resource-card view registers the @script-wrapped hover listener', function () {
    // <x-filament-widgets::widgets> lazy-mounts each chart widget, so the
    // @script content never appears in the initial HTTP response or in a
    // Livewire::test() snapshot. assert directly against the source so a
    // refactor that drops @script (which livewire would silently strip
    // from a class-based component) gets caught at the test layer.
    $blade = file_get_contents(resource_path('views/filament/server/widgets/resource-card.blade.php'));

    expect($blade)
        ->toContain('@script')
        ->toContain('@endscript')
        ->toContain('__ospOverviewHoverBound');
});

test('CPU chart widget renders the hover overlay markup', function () {
    [$user, $server] = runningStateSeed();
    // prime a series so the chart has data to render
    cache()->put("servers.{$server->id}.cpu_absolute", [
        1700000000 => 12.5,
        1700000001 => 73.0,
    ]);

    $this->actingAs($user);
    Filament::setTenant($server);

    // the @script block lives outside the morphed widget HTML so it
    // isn't in this assertion path — covered by a manual smoke test.
    Livewire::test(ServerCpuChart::class, ['server' => $server])
        ->assertSee('overview-resource-card__plot', escape: false)
        ->assertSee('data-pts=', escape: false)
        ->assertSee('data-labels=', escape: false)
        ->assertSee('overview-resource-card__hover', escape: false)
        ->assertSee('overview-resource-card__tooltip', escape: false)
        ->assertSee('data-tooltip', escape: false);
});

test('CPU chart widget renders the data-times attribute and tooltip time row', function () {
    [$user, $server] = runningStateSeed();
    cache()->put("servers.{$server->id}.cpu_absolute", [
        1700000000 => 12.5,
        1700000005 => 73.0,
        1700000010 => 41.2,
    ]);

    $this->actingAs($user);
    Filament::setTenant($server);

    Livewire::test(ServerCpuChart::class, ['server' => $server])
        ->assertSee('data-times=', escape: false)
        ->assertSee('overview-resource-card__tooltip-time', escape: false);
});
