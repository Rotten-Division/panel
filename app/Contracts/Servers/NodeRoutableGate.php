<?php

namespace App\Contracts\Servers;

interface NodeRoutableGate
{
    /**
     * Whether a server may be placed on this node, i.e. the node has a
     * validated routing backend. The default (no router) returns true; the
     * allocation-router rebinds this to require a wg-peer row.
     */
    public function routable(int $nodeId): bool;
}
