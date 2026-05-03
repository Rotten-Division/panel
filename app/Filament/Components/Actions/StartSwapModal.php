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
        return $action
            ->requiresConfirmation(fn (Server $server) => $blockingServerFor($server) !== null)
            ->modalHidden(fn (Server $server) => $blockingServerFor($server) === null)
            ->modalHeading(trans('server/console.power_actions.start_swap_heading'))
            ->modalDescription(function (Server $server) use ($blockingServerFor) {
                $other = $blockingServerFor($server);

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
