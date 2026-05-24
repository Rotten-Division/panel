<?php

namespace App\Services\Servers;

use App\Contracts\Servers\StashMembershipGate;
use App\Models\Server;
use App\Models\User;

class NoStashMembershipGate implements StashMembershipGate
{
    public function blockingServerFor(User $user, ?Server $aboutTo = null): ?Server
    {
        return null;
    }
}
