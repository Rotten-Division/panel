<?php

namespace App\Contracts\Servers;

use App\Models\Server;
use App\Models\User;
use App\Services\Servers\StartGateDecision;
use Closure;

/**
 * gate the start of a server through whatever policy is currently active.
 * the panel binds an unrestricted default and the user limits plugin rebinds
 * to a swap aware implementation that serialises starts per owner and stops
 * a blocking server before allowing the new one through.
 *
 * callers do not need to know whether a swap fired or whether any policy is
 * even installed, the gate handles every prerequisite and only invokes the
 * perform closure when the start should actually proceed.
 */
interface ServerStartGate
{
    public function gateStart(Server $server, ?User $acting, Closure $perform): StartGateDecision;

    /**
     * dry run query, returns the server that would block a start of $server
     * without actually starting anything. used by ui surfaces to decide
     * whether to render a swap confirmation modal. returns null when nothing
     * would block, including when no policy is installed.
     */
    public function wouldBlock(Server $server, ?User $acting): ?Server;
}
