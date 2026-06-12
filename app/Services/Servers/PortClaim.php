<?php

namespace App\Services\Servers;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PortClaim
{
    // held at most this long per lock; longer than any bind transaction
    // should reasonably take. tune in one place if a bind ever grows.
    public const LOCK_TTL = 15;

    // wait this long to acquire before failing the claim cleanly.
    public const BLOCK_TIMEOUT = 10;

    /**
     * The only sanctioned way to flip allocation.server_id null to set.
     * Acquires the per-port locks sorted ascending and deduped (so any two
     * operations over overlapping ports lock in the same order and cannot
     * deadlock), runs $bind inside one DB transaction, releases after the
     * transaction completes (commit or rollback).
     * No wings or edge calls belong inside $bind, the route push is post-commit
     * via the AllocationsAssigned event.
     *
     * @template T
     *
     * @param  list<int>  $ports
     * @param  Closure():T  $bind
     * @return T
     */
    public function withClaims(array $ports, Closure $bind): mixed
    {
        $ports = array_values(array_unique(array_map('intval', $ports)));
        sort($ports);

        $locks = [];
        try {
            foreach ($ports as $port) {
                $lock = Cache::lock("ospite:port-reserve:{$port}", self::LOCK_TTL);
                $lock->block(self::BLOCK_TIMEOUT);
                $locks[] = $lock;
            }

            return DB::transaction($bind);
        } finally {
            foreach (array_reverse($locks) as $lock) {
                $lock->release();
            }
        }
    }
}
