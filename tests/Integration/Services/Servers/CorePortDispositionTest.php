<?php

namespace App\Tests\Integration\Services\Servers;

use App\Contracts\Servers\PortHoldGate;
use App\Enums\PortState;
use App\Models\Allocation;
use App\Services\Servers\CorePortDisposition;
use App\Tests\Integration\IntegrationTestCase;

class CorePortDispositionTest extends IntegrationTestCase
{
    /**
     * @param  list<array{pool: string, port: int}>  $held
     */
    private function disposition(array $held = []): CorePortDisposition
    {
        $gate = new class($held) implements PortHoldGate
        {
            /**
             * @param  list<array{pool: string, port: int}>  $held
             */
            public function __construct(private array $held) {}

            public function held(): array
            {
                return $this->held;
            }
        };

        return new CorePortDisposition($gate);
    }

    public function test_a_port_bound_on_any_node_is_bound(): void
    {
        $server = $this->createServerModel();
        Allocation::factory()->create(['node_id' => $server->node_id, 'port' => 25600, 'server_id' => $server->id]);

        $this->assertSame(PortState::Bound, $this->disposition()->for(25600));
    }

    public function test_a_held_port_with_no_binding_is_held(): void
    {
        $d = $this->disposition([['pool' => 'java_backend', 'port' => 25601]]);

        $this->assertSame(PortState::Held, $d->for(25601));
    }

    public function test_a_port_neither_bound_nor_held_is_free(): void
    {
        $this->assertSame(PortState::Free, $this->disposition()->for(25602));
    }

    public function test_bound_wins_over_held(): void
    {
        $server = $this->createServerModel();
        Allocation::factory()->create(['node_id' => $server->node_id, 'port' => 25603, 'server_id' => $server->id]);

        $d = $this->disposition([['pool' => 'java_backend', 'port' => 25603]]);

        $this->assertSame(PortState::Bound, $d->for(25603));
    }
}
