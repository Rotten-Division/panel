<?php

namespace App\Services\Servers;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;

// short-lived per-server cache of an in-flight retrieve. the panel seeds
// the requester + destination at retrieve start; wings merges streaming
// byte counts on top. entry is cleared explicitly by the restored callback
// handler in the stash plugin; the ttl is the safety net.
class RetrieveProgressCache
{
    private const TTL_SECONDS = 600;

    /** @param array<string, mixed> $meta */
    public function seed(Server $server, array $meta): void
    {
        $existing = $this->get($server) ?? [];
        Cache::put($this->key($server), array_merge($existing, $meta), self::TTL_SECONDS);
    }

    /** @param array{step: string, bytes: int, total_bytes: int} $progress */
    public function mergeProgress(Server $server, array $progress): void
    {
        $existing = $this->get($server) ?? [];
        $step = $progress['step'] ?? null;

        if ($step === 'downloading' && !isset($existing['streaming_started_at'])) {
            $existing['streaming_started_at'] = now()->getTimestamp();
        }

        if ($step === 'starting') {
            // the boot tick carries no byte counts; keep the streamed totals and
            // stamp when streaming finished so the done step can report duration.
            $existing['streaming_finished_at'] ??= now()->getTimestamp();
            unset($progress['bytes'], $progress['total_bytes']);
        }

        Cache::put($this->key($server), array_merge($existing, $progress), self::TTL_SECONDS);
    }

    /** @return array<string, mixed>|null */
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
        return "server:{$server->uuid}:retrieve-progress";
    }
}
