<?php

namespace App\Services\Servers;

use App\Contracts\Servers\ServerStartGate;
use App\Models\Server;
use App\Models\User;
use Closure;

/**
 * default ServerStartGate binding, lets every start through without any
 * concurrency or swap checks. the user limits plugin rebinds the contract
 * to its swap aware implementation when installed, this default keeps the
 * panel functional when no policy plugin is loaded.
 */
class UnrestrictedServerStartGate implements ServerStartGate
{
    public function gateStart(Server $server, ?User $acting, Closure $perform): StartGateDecision
    {
        $perform();

        return StartGateDecision::allowed();
    }

    public function wouldBlock(Server $server, ?User $acting): ?Server
    {
        return null;
    }
}
