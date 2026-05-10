<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\NestMembershipGate;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\NoNestMembershipGate;
use App\Tests\Integration\IntegrationTestCase;

class NestMembershipGateTest extends IntegrationTestCase
{
    public function test_default_returns_null_blocking_server(): void
    {
        $user = User::factory()->create();
        $gate = new NoNestMembershipGate();

        $this->assertNull($gate->blockingServerFor($user));
        $this->assertNull($gate->blockingServerFor($user, Server::factory()->make()));
    }

    public function test_default_binding_implements_contract(): void
    {
        $this->assertInstanceOf(NestMembershipGate::class, new NoNestMembershipGate());
    }
}
