<?php

namespace App\Tests\Integration\Jobs\Schedule;

use App\Console\Commands\Schedule\PollDaemonHealthCommand;
use App\Events\Node\NodeHealthChecked;
use App\Events\Node\NodeReconnected;
use App\Events\Node\NodeWentDown;
use App\Models\Node;
use App\Tests\Integration\IntegrationTestCase;
use Carbon\Carbon;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class PollDaemonHealthCommandTest extends IntegrationTestCase
{
    /**
     * Build a successful wings system response carrying the User-Agent header
     * the daemon repository validates with the node's daemon_token_id.
     */
    private function fakeWingsOk(Node $node): array
    {
        return [
            'response' => [
                'architecture' => 'x86_64',
                'cpu_count' => 4,
                'kernel_version' => '6.1.0',
                'os' => 'linux',
                'version' => '1.0.0',
            ],
            'headers' => [
                'User-Agent' => "Pelican Wings/v1.0.0 (id:$node->daemon_token_id)",
            ],
        ];
    }

    public function test_handle_returns_success_when_no_nodes_exist(): void
    {
        Node::query()->delete();

        $exit = $this->artisan(PollDaemonHealthCommand::class)->run();

        $this->assertSame(0, $exit);
    }

    public function test_reachable_node_updates_last_seen_and_fires_health_checked(): void
    {
        Event::fake([NodeHealthChecked::class, NodeReconnected::class, NodeWentDown::class]);

        $node = Node::factory()->create(['last_seen' => null, 'maintenance_mode' => false]);
        $ok = $this->fakeWingsOk($node);

        Http::fake(fn (Request $request) => str_contains($request->url(), $node->fqdn)
            ? Http::response($ok['response'], 200, $ok['headers'])
            : Http::response('', 500));

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        $node->refresh();
        $this->assertNotNull($node->last_seen);
        $this->assertTrue(Carbon::now()->diffInSeconds($node->last_seen) <= 5);

        Event::assertDispatched(
            NodeHealthChecked::class,
            fn (NodeHealthChecked $event) => $event->node->is($node) && $event->reachable === true,
        );
    }

    public function test_reachable_node_that_was_previously_down_dispatches_reconnected(): void
    {
        Event::fake([NodeHealthChecked::class, NodeReconnected::class, NodeWentDown::class]);

        $node = Node::factory()->create([
            'last_seen' => Carbon::now()->subSeconds(600),
            'maintenance_mode' => false,
        ]);
        $ok = $this->fakeWingsOk($node);

        Http::fake([
            '*' => Http::response($ok['response'], 200, $ok['headers']),
        ]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        Event::assertDispatched(
            NodeReconnected::class,
            fn (NodeReconnected $event) => $event->node->is($node)
                && $event->previousLastSeen !== null
                && $event->previousLastSeen->diffInSeconds(Carbon::now()->subSeconds(600)) <= 5,
        );
        Event::assertNotDispatched(NodeWentDown::class);
    }

    public function test_reachable_node_that_was_already_healthy_does_not_dispatch_reconnected(): void
    {
        Event::fake([NodeHealthChecked::class, NodeReconnected::class, NodeWentDown::class]);

        $node = Node::factory()->create([
            'last_seen' => Carbon::now()->subSeconds(30),
            'maintenance_mode' => false,
        ]);
        $ok = $this->fakeWingsOk($node);

        Http::fake([
            '*' => Http::response($ok['response'], 200, $ok['headers']),
        ]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        Event::assertDispatched(NodeHealthChecked::class);
        Event::assertNotDispatched(NodeReconnected::class);
        Event::assertNotDispatched(NodeWentDown::class);
    }

    public function test_unreachable_node_that_was_healthy_dispatches_went_down(): void
    {
        Event::fake([NodeHealthChecked::class, NodeReconnected::class, NodeWentDown::class]);

        $previous = Carbon::now()->subSeconds(30);
        $node = Node::factory()->create([
            'last_seen' => $previous,
            'maintenance_mode' => false,
        ]);

        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        $node->refresh();
        $this->assertSame($previous->getTimestamp(), $node->last_seen->getTimestamp());

        Event::assertDispatched(
            NodeWentDown::class,
            fn (NodeWentDown $event) => $event->node->is($node) && $event->reason !== null,
        );
        Event::assertDispatched(
            NodeHealthChecked::class,
            fn (NodeHealthChecked $event) => $event->reachable === false,
        );
        Event::assertNotDispatched(NodeReconnected::class);
    }

    public function test_unreachable_node_that_was_already_down_only_dispatches_health_checked(): void
    {
        Event::fake([NodeHealthChecked::class, NodeReconnected::class, NodeWentDown::class]);

        Node::factory()->create([
            'last_seen' => Carbon::now()->subSeconds(600),
            'maintenance_mode' => false,
        ]);

        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        Event::assertDispatched(NodeHealthChecked::class);
        Event::assertNotDispatched(NodeWentDown::class);
        Event::assertNotDispatched(NodeReconnected::class);
    }

    public function test_maintenance_nodes_are_skipped_entirely(): void
    {
        Event::fake([NodeHealthChecked::class]);

        Node::factory()->create([
            'last_seen' => null,
            'maintenance_mode' => true,
        ]);

        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        Event::assertNotDispatched(NodeHealthChecked::class);
    }

    public function test_successful_poll_warms_the_system_information_cache(): void
    {
        $node = Node::factory()->create(['last_seen' => null, 'maintenance_mode' => false]);
        $ok = $this->fakeWingsOk($node);

        Http::fake([
            '*' => Http::response($ok['response'], 200, $ok['headers']),
        ]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        $cached = cache()->get("nodes.$node->id.system_information");
        $this->assertIsArray($cached);
        $this->assertSame('1.0.0', $cached['version']);
    }

    public function test_failed_poll_writes_exception_payload_to_cache(): void
    {
        $node = Node::factory()->create(['last_seen' => null, 'maintenance_mode' => false]);

        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan(PollDaemonHealthCommand::class)->assertSuccessful();

        $cached = cache()->get("nodes.$node->id.system_information");
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('exception', $cached);
    }
}
