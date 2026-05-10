<?php

namespace App\Contracts\Servers;

use App\Models\Server;
use App\Models\User;

/**
 * enforces the one-out-of-the-nest invariant. at most one server per user
 * may sit outside the nest at any moment, regardless of power state. the
 * nest manager plugin rebinds the default to a real reader against the
 * servers table, the wizard and restore flows consult the gate before
 * adding a new non-nest server and trigger a swap eviction when needed.
 */
interface NestMembershipGate
{
    /**
     * returns the existing non-nest server that would block bringing
     * $aboutTo out of the nest, or null if nothing blocks.
     *
     * pass $aboutTo = null to ask "is the user creating a new server,
     * what existing non-nest server would block them?".
     */
    public function blockingServerFor(User $user, ?Server $aboutTo = null): ?Server;
}
