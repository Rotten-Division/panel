<?php

namespace App\Contracts\Servers;

use App\Models\Server;

interface PlayerCountProvider
{
    /**
     * resolve the current and max player count for the given server.
     * implementations may return null when the data is unavailable
     * (server offline, plugin not installed, query timed out, etc).
     *
     * @return array{current: ?int, max: ?int}|null
     */
    public function resolve(Server $server): ?array;
}
