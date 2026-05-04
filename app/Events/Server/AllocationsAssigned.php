<?php

namespace App\Events\Server;

use App\Events\Event;
use App\Models\Server;
use Illuminate\Queue\SerializesModels;

class AllocationsAssigned extends Event
{
    use SerializesModels;

    /**
     * @param  array<int, int>  $allocationIds
     */
    public function __construct(public Server $server, public array $allocationIds) {}
}
