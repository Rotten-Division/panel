<?php

namespace App\Contracts\Servers;

interface NodeRoutableGate
{
    /**
     * The default (no router) returns true; the allocation-router rebinds this
     * to require a wg-peer row.
     */
    public function routable(int $nodeId): bool;
}
