<?php

namespace App\Services\Servers;

use App\Contracts\Servers\NestMembershipGate;
use App\Models\Server;
use App\Models\User;

class NoNestMembershipGate implements NestMembershipGate
{
    public function blockingServerFor(User $user, ?Server $aboutTo = null): ?Server
    {
        return null;
    }
}
