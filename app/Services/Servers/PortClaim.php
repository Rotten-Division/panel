<?php

namespace App\Services\Servers;

use App\Models\Allocation;
use Closure;
use Illuminate\Support\Facades\DB;

class PortClaim
{
    /**
     * Bind a set of ports under a fleet-wide database row lock. The allocation
     * rows for $ports are locked FOR UPDATE in ascending port order (deadlock-free)
     * inside the transaction, so the bind serialises against any concurrent claim
     * across every node, the locks hold until the real outermost commit (savepoints
     * do not release them, so this is safe when a caller is already in a transaction),
     * and there is no lease to expire. $bind runs with the rows locked and does its
     * own disposition re-check before the server_id flip.
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

        return DB::transaction(function () use ($ports, $bind) {
            if ($ports !== []) {
                Allocation::query()->whereIn('port', $ports)->orderBy('port')->lockForUpdate()->get();
            }

            return $bind();
        });
    }
}
