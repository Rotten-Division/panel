<?php

namespace App\Contracts\Servers;

use App\Models\Server;

interface StashedArchiveLocator
{
    // bytes of the server's cold-storage archive, or null when it has no
    // stash archive. core default returns null; the stash-manager plugin
    // rebinds this to read StashedServer.archive_size. keeps panel core
    // free of any plugin import (direction is core→plugin only).
    public function archivedBytesFor(Server $server): ?int;
}
