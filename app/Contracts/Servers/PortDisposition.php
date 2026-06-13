<?php

namespace App\Contracts\Servers;

use App\Enums\PortState;

interface PortDisposition
{
    /**
     * Precedence is highest-reality-first: Bound > Held > Reserved > Free >
     * OutOfPool. The core default never returns Reserved or OutOfPool (no pool
     * knowledge); the allocation-router rebinds this to add them.
     */
    public function for(int $port): PortState;
}
