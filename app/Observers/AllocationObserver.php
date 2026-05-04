<?php

namespace App\Observers;

use App\Events\Server\AllocationsAssigned;
use App\Events\Server\AllocationsReleased;
use App\Models\Allocation;
use App\Models\Server;

class AllocationObserver
{
    public function created(Allocation $allocation): void
    {
        if ($allocation->server_id === null) {
            return;
        }

        $server = Server::find($allocation->server_id);
        if ($server !== null) {
            event(new AllocationsAssigned($server, [$allocation->id]));
        }
    }

    public function updated(Allocation $allocation): void
    {
        if (!$allocation->wasChanged('server_id')) {
            return;
        }

        $original = $allocation->getOriginal('server_id');
        $now = $allocation->server_id;

        if ($original === null && $now !== null) {
            $server = Server::find($now);
            if ($server !== null) {
                event(new AllocationsAssigned($server, [$allocation->id]));
            }

            return;
        }

        if ($original !== null && $now === null) {
            $server = Server::find($original);
            if ($server !== null) {
                event(new AllocationsReleased($server, [$allocation->id]));
            }
        }
    }
}
