<?php

namespace App\Services\Servers;

use App\Contracts\Servers\PortHoldGate;

class NoPortHoldsGate implements PortHoldGate
{
    public function held(): array
    {
        return [];
    }
}
