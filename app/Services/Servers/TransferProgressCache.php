<?php

namespace App\Services\Servers;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;

// short-lived cache of in-progress transfer state per server. ttl is
// longer than the typical transfer (a few minutes) so a quiet phase
// does not look stale; entries get cleared explicitly on completion
// by the panel-side transfer success/failure handler.
class TransferProgressCache
{
    private const TTL_SECONDS = 600;

    /** @param array{step: string, bytes: int, total_bytes: int} $payload */
    public function put(Server $server, array $payload): void
    {
        Cache::put($this->key($server), $payload, self::TTL_SECONDS);
    }

    /** @return array{step: string, bytes: int, total_bytes: int}|null */
    public function get(Server $server): ?array
    {
        return Cache::get($this->key($server));
    }

    public function forget(Server $server): void
    {
        Cache::forget($this->key($server));
    }

    private function key(Server $server): string
    {
        return "server:{$server->uuid}:transfer-progress";
    }
}
