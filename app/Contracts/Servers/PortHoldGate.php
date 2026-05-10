<?php

namespace App\Contracts\Servers;

/**
 * exposes the set of (pool, port) pairs the panel is holding against
 * allocation reservation. the wizard's allocation resolver consults this
 * before handing a port to a new server, so a port reserved for a nest
 * server's eventual restore is not given to a different server in the
 * meantime.
 *
 * default binding returns an empty list, the nest plugin rebinds to a
 * real implementation that reads osnm_port_holds.
 */
interface PortHoldGate
{
    /**
     * @return list<array{pool: string, port: int}>
     */
    public function held(): array;
}
