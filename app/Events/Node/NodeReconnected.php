<?php

namespace App\Events\Node;

use App\Events\Event;
use App\Models\Node;
use Carbon\Carbon;
use Illuminate\Queue\SerializesModels;

class NodeReconnected extends Event
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Node $node, public ?Carbon $previousLastSeen) {}
}
