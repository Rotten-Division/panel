<?php

namespace App\Filament\Components\Actions;

use App\Models\Server;
use Closure;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

// shared swap modal configuration applied to start actions on the server
// console and the dashboard power dropdown, both surfaces present the same
// ux when the ospite user limits gate fires so the copy and styling lives
// in one place.
class StartSwapModal
{
    public static function configure(Action $action, Closure $blockingServerFor): Action
    {
        // memoise the blocker resolution per server id, requiresConfirmation
        // modalHeading modalDescription and modalSubmitActionLabel all need
        // the same lookup and without the cache the swap gate runs four
        // times per server per render on the dashboard table.
        $cache = [];
        $resolve = function (Server $server) use (&$cache, $blockingServerFor) {
            $key = $server->id;

            if (!array_key_exists($key, $cache)) {
                $cache[$key] = $blockingServerFor($server);
            }

            return $cache[$key];
        };

        // every modal facing knob is conditional on the blocker existing.
        // filament v4 enters modal mode the moment a non null modalHeading
        // or modalDescription is set on an action, and once in modal mode
        // an action with no way to render the modal silently unmounts on
        // click without invoking the closure. returning null from each of
        // these getters keeps the action out of modal mode entirely when
        // there is no blocker, so a click runs the action immediately.
        return $action
            ->requiresConfirmation(fn (Server $server) => $resolve($server) !== null)
            ->modalHeading(fn (Server $server) => $resolve($server) !== null
                ? trans('server/console.power_actions.start_swap_heading')
                : null)
            ->modalDescription(function (Server $server) use ($resolve): ?HtmlString {
                $other = $resolve($server);

                if ($other === null) {
                    return null;
                }

                // wrap in HtmlString so the code tag in the trans string
                // renders as monospace, escape the server name first
                // because trans does not auto escape.
                return new HtmlString(trans('server/console.power_actions.start_swap_description', ['name' => e($other->name)]));
            })
            ->modalCloseButton(false)
            ->modalSubmitActionLabel(fn (Server $server) => $resolve($server) !== null
                ? trans('server/console.power_actions.start_swap_submit')
                : null);
    }
}
