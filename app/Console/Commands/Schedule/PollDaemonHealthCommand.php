<?php

namespace App\Console\Commands\Schedule;

use App\Events\Node\NodeHealthChecked;
use App\Events\Node\NodeReconnected;
use App\Events\Node\NodeWentDown;
use App\Models\Node;
use App\Repositories\Daemon\DaemonSystemRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class PollDaemonHealthCommand extends Command
{
    protected $signature = 'p:node:poll-health';

    protected $description = 'Poll every non-maintenance node for liveness, update last_seen and dispatch health events.';

    public function handle(): int
    {
        $configured = config('panel.nodes.health_threshold_seconds');
        $threshold = is_numeric($configured) && (int) $configured > 0 ? (int) $configured : 120;

        $nodes = Node::query()
            ->where('maintenance_mode', false)
            ->get();

        if ($nodes->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($nodes as $node) {
            $this->pollNode($node, $threshold);
        }

        return self::SUCCESS;
    }

    /**
     * Poll a single node and emit the appropriate events. Bypasses the
     * Node::systemInformation() cache so every poll reflects current
     * liveness, then writes the result back into the same cache key so
     * UI consumers stay warm.
     */
    protected function pollNode(Node $node, int $threshold): void
    {
        $previousLastSeen = $node->last_seen;
        $wasHealthy = $node->isHealthy($threshold);

        $reachable = false;
        $reason = null;
        $payload = [];

        try {
            $payload = (new DaemonSystemRepository())->setNode($node)->getSystemInformation();
            $reachable = true;
        } catch (Throwable $exception) {
            $reason = $exception->getMessage();
            $payload = ['exception' => $reason];
        }

        cache()->put("nodes.$node->id.system_information", $payload, now()->addSeconds(360));

        if ($reachable) {
            $node->forceFill(['last_seen' => Carbon::now()])->save();
        }

        event(new NodeHealthChecked($node, $reachable));

        if ($reachable && !$wasHealthy) {
            event(new NodeReconnected($node, $previousLastSeen));
        }

        if (!$reachable && $wasHealthy) {
            event(new NodeWentDown($node, $reason));
        }
    }
}
