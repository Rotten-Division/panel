<?php

namespace App\Http\Middleware;

use App\Filament\Server\Pages\Overview;
use App\Models\Server;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectServerConflictToOverview
{
    public function handle(Request $request, Closure $next): Response
    {
        $server = Filament::getTenant();

        // every server page except the overview assumes an operational server
        // with a node and allocation. in a conflict state (stashed, installing,
        // transferring, ...) the node is gone, so those pages 403 on canAccess
        // and then 500 dereferencing the null node. the overview is the one page
        // that renders each conflict state, so route the user there instead.
        if ($server instanceof Server
            && $server->isInConflictState()
            && !$request->routeIs('filament.server.pages.overview')) {
            return redirect(Overview::getUrl(panel: 'server', tenant: $server));
        }

        return $next($request);
    }
}
