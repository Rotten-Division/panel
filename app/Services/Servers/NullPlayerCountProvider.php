<?php

namespace App\Services\Servers;

use App\Contracts\Servers\PlayerCountProvider;
use App\Models\Server;

// default binding when no live player count plugin is installed. always
// returns null so the overview page falls back to the placeholder.
class NullPlayerCountProvider implements PlayerCountProvider
{
    public function resolve(Server $server): ?array
    {
        return null;
    }
}
