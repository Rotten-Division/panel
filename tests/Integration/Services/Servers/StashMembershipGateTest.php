<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\StashMembershipGate;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\NoStashMembershipGate;
use App\Tests\Integration\IntegrationTestCase;

class StashMembershipGateTest extends IntegrationTestCase
{
    public function test_default_returns_null_blocking_server(): void
    {
        $user = User::factory()->create();
        $gate = new NoStashMembershipGate();

        $this->assertNull($gate->blockingServerFor($user));
        $this->assertNull($gate->blockingServerFor($user, Server::factory()->make()));
    }

    public function test_default_binding_implements_contract(): void
    {
        $this->assertInstanceOf(StashMembershipGate::class, new NoStashMembershipGate());
    }
}
