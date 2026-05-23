<?php

namespace App\Contracts\Servers;

use App\Models\Server;
use Filament\Actions\Action;
use Illuminate\Contracts\View\View;

// plugins contribute a state handler when they want to own the entire
// overview body for a given server state. handlers are checked in
// registration order, first match wins. the page caches each handler's
// actions at mount time so blade views can dispatch them via
// mountAction('name') without panel core knowing the plugin's job
// classes.
interface OverviewStateHandler
{
    // return true if this handler should render the body for the given
    // server. multiple handlers can match in principle, the first
    // registered wins per request.
    public function handles(Server $server): bool;

    // returns the view that owns the page body. rendered inside the
    // x-filament-panels::page wrapper, otherwise has full control.
    public function render(Server $server): View;

    // actions this handler contributes for the current server state.
    // wake, swap-confirm, manual-trigger, etc. cached on the page via
    // cacheAction() at mount so blade views can call
    // wire:click="mountAction('wake')" without dispatching events the
    // panel does not know about.
    //
    // @return array<Action>
    public function actions(Server $server): array;
}
