<?php

namespace App\Events\Node;

use App\Events\Event;
use App\Models\Node;
use Illuminate\Queue\SerializesModels;

class NodeWentDown extends Event
{
    use SerializesModels;

    public function __construct(public Node $node, public ?string $reason) {}
}
