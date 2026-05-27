<?php

namespace App\Services\Servers;

use App\Contracts\Servers\StashedArchiveLocator;
use App\Models\Server;

class NullStashedArchiveLocator implements StashedArchiveLocator
{
    public function archivedBytesFor(Server $server): ?int
    {
        return null;
    }
}
