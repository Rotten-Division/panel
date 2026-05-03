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
        // modalHidden and modalDescription each evaluate the predicate once
        // per render so without this cache the swap gate runs three times
        // per row on the dashboard table.
        $cache = [];
        $resolve = function (Server $server) use (&$cache, $blockingServerFor) {
            $key = $server->id;

            if (!array_key_exists($key, $cache)) {
                $cache[$key] = $blockingServerFor($server);
            }

            return $cache[$key];
        };

        return $action
            ->requiresConfirmation(fn (Server $server) => $resolve($server) !== null)
            ->modalHidden(fn (Server $server) => $resolve($server) === null)
            ->modalHeading(trans('server/console.power_actions.start_swap_heading'))
            ->modalDescription(function (Server $server) use ($resolve) {
                $other = $resolve($server);

                // wrap in HtmlString so the code tag in the trans string
                // renders as monospace, escape the server name first
                // because trans does not auto escape.
                return $other
                    ? new HtmlString(trans('server/console.power_actions.start_swap_description', ['name' => e($other->name)]))
                    : null;
            })
            ->modalCloseButton(false)
            ->modalSubmitActionLabel(trans('server/console.power_actions.start_swap_submit'));
    }
}
