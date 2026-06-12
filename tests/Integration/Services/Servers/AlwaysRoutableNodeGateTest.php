<?php

namespace App\Tests\Integration\Services\Servers;

use App\Services\Servers\AlwaysRoutableNodeGate;
use App\Tests\Integration\IntegrationTestCase;

class AlwaysRoutableNodeGateTest extends IntegrationTestCase
{
    public function test_every_node_is_routable_without_a_router(): void
    {
        $gate = new AlwaysRoutableNodeGate();

        $this->assertTrue($gate->routable(1));
        $this->assertTrue($gate->routable(999));
    }
}
