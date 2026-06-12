<?php

namespace App\Services\Servers;

use App\Contracts\Servers\PortDisposition;
use App\Contracts\Servers\PortHoldGate;
use App\Enums\PortState;
use App\Models\Allocation;

class CorePortDisposition implements PortDisposition
{
    public function __construct(private readonly PortHoldGate $holds) {}

    public function for(int $port): PortState
    {
        if (Allocation::query()->where('port', $port)->whereNotNull('server_id')->exists()) {
            return PortState::Bound;
        }

        foreach ($this->holds->held() as $hold) {
            if ((int) $hold['port'] === $port) {
                return PortState::Held;
            }
        }

        return PortState::Free;
    }
}
