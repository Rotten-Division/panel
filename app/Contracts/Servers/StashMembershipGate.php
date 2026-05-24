<?php

namespace App\Contracts\Servers;

use App\Models\Server;
use App\Models\User;

/**
 * enforces the one-out-of-stash invariant. at most one server per user
 * may sit outside cold storage at any moment, regardless of power state.
 * the stash manager plugin rebinds the default to a real reader against
 * the servers table, the wizard and retrieval flows consult the gate
 * before adding a new non-stashed server and trigger a swap stash when
 * needed.
 */
interface StashMembershipGate
{
    /**
     * returns the existing non-stashed server that would block bringing
     * $aboutTo out of cold storage, or null if nothing blocks.
     *
     * pass $aboutTo = null to ask "is the user creating a new server,
     * what existing non-stashed server would block them?".
     */
    public function blockingServerFor(User $user, ?Server $aboutTo = null): ?Server;
}
