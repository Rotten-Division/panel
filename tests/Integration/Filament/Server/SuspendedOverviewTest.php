<?php

use App\Contracts\Servers\StashedArchiveLocator;
use App\Enums\ServerState;
use App\Models\Server;
use App\Tests\Integration\IntegrationTestCase;

uses(IntegrationTestCase::class);

function suspendedServer(): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $server = Server::factory()->for($user, 'user')->create([
        'status' => ServerState::Suspended,
    ]);

    return [$user, $server];
}

test('suspended renders the stage hero with brick variant', function () {
    [$user, $server] = suspendedServer();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Service suspended.', escape: false);
});

test('suspended renders the reason card', function () {
    [$user, $server] = suspendedServer();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Reason for suspension', escape: false);
});

test('suspended renders the degraded fact grid with data retained dash', function () {
    [$user, $server] = suspendedServer();

    // null locator is the default binding so no archive → data retained shows —
    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('Data retained', escape: false)
        ->assertSee('Case reference', escape: false)
        ->assertSee('Contact support', escape: false);
});

test('suspended shows the review suspension notice wire target', function () {
    [$user, $server] = suspendedServer();

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee("mountAction('reviewSuspensionNotice')", escape: false);
});

test('suspended data retained shows formatted bytes when locator returns a value', function () {
    [$user, $server] = suspendedServer();

    // rebind the locator for this test to simulate a server with an archive.
    // 1.5 GiB = 1610612736 bytes → "1.50 GiB"
    $this->app->singleton(StashedArchiveLocator::class, function () {
        return new class implements StashedArchiveLocator
        {
            public function archivedBytesFor(Server $server): ?int
            {
                return 1_610_612_736;
            }
        };
    });

    $this->actingAs($user)
        ->get("/server/{$server->uuid_short}/overview")
        ->assertOk()
        ->assertSee('1.50 GiB', escape: false);
});
