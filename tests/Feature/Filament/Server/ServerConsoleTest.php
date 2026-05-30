<?php

use App\Filament\Server\Widgets\ServerConsole;
use App\Models\Egg;
use App\Models\Server;
use Filament\Facades\Filament;
use Livewire\Livewire;

function consoleWidgetSeed(): array
{
    [$user] = generateTestAccount();
    $user->forceFill(['email_verified_at' => now()])->save();
    $egg = Egg::factory()->withGameTag('minecraft')->create();
    $server = Server::factory()->for($user, 'user')->create([
        'egg_id' => $egg->id,
        'status' => null,
    ]);

    return [$user, $server];
}

it('hides the command input when readOnly', function () {
    [$user, $server] = consoleWidgetSeed();

    $this->actingAs($user);
    Filament::setTenant($server);

    Livewire::test(ServerConsole::class, ['server' => $server, 'user' => $user, 'readOnly' => true])
        ->assertDontSee('id="send-command"', escape: false);
});

it('shows the command input when not readOnly for a permitted user', function () {
    [$user, $server] = consoleWidgetSeed();

    $this->actingAs($user);
    Filament::setTenant($server);

    Livewire::test(ServerConsole::class, ['server' => $server, 'user' => $user, 'readOnly' => false])
        ->assertSee('id="send-command"', escape: false);
});
