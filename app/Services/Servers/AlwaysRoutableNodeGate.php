<?php

namespace App\Services\Servers;

use App\Contracts\Servers\NodeRoutableGate;

class AlwaysRoutableNodeGate implements NodeRoutableGate
{
    public function routable(int $nodeId): bool
    {
        return true;
    }
}
