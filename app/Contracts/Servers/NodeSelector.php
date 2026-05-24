<?php

namespace App\Contracts\Servers;

use App\Models\Node;
use App\Models\Server;

/**
 * picks a destination wings node for a server about to be placed or retrieved.
 * default binding walks healthy viable nodes in id order and returns the
 * first. the stash manager plugin rebinds to a least loaded scorer that
 * compares free resource fractions across the three dimensions.
 */
interface NodeSelector
{
    /**
     * @param  list<int>|null  $eligibleNodeIds  optional restriction to a subset of node ids.
     */
    public function selectFor(Server $server, ?array $eligibleNodeIds = null): ?Node;

    /**
     * per node viability score, null when the node cannot host the server.
     */
    public function score(Node $node, Server $server): ?float;
}
