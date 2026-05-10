<?php

namespace App\Services\Servers;

use App\Contracts\Servers\NodeSelector;
use App\Models\Node;
use App\Models\Server;

class FirstAvailableNodeSelector implements NodeSelector
{
    public function selectFor(Server $server, ?array $eligibleNodeIds = null): ?Node
    {
        $query = Node::query()
            ->where('maintenance_mode', false)
            ->withSum('servers', 'memory')
            ->withSum('servers', 'disk')
            ->withSum('servers', 'cpu')
            ->orderBy('id');

        if ($eligibleNodeIds !== null) {
            $query->whereIn('id', $eligibleNodeIds);
        }

        foreach ($query->lazy(50) as $node) {
            if ($node->isViable($server->memory, $server->disk, $server->cpu)) {
                return $node;
            }
        }

        return null;
    }

    public function score(Node $node, Server $server): ?float
    {
        if ($node->maintenance_mode) {
            return null;
        }

        if (!$node->isViable($server->memory, $server->disk, $server->cpu)) {
            return null;
        }

        return 0.0;
    }
}
