<?php

namespace App\Tests\Unit\Contracts\Servers;

use App\Contracts\Servers\PortHoldGate;
use App\Services\Servers\NoPortHoldsGate;
use App\Tests\TestCase;

class PortHoldGateTest extends TestCase
{
    public function test_default_binding_returns_empty_holds(): void
    {
        $gate = new NoPortHoldsGate();
        $this->assertSame([], $gate->held());
    }

    public function test_default_binding_implements_contract(): void
    {
        $this->assertInstanceOf(PortHoldGate::class, new NoPortHoldsGate());
    }
}
