<?php

namespace App\Tests\Integration\Events\Server;

use App\Events\Server\AllocationsAssigned;
use App\Events\Server\AllocationsReleased;
use App\Http\Controllers\Api\Client\Servers\NetworkAllocationController;
use App\Http\Requests\Api\Client\Servers\Network\DeleteAllocationRequest;
use App\Models\Allocation;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Databases\DatabaseManagementService;
use App\Services\Servers\BuildModificationService;
use App\Services\Servers\ServerDeletionService;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;

class AllocationLifecycleEventsTest extends IntegrationTestCase
{
    public function test_eloquent_save_with_server_id_set_fires_allocations_assigned(): void
    {
        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $server = $this->createServerModel();
        $allocation = Allocation::factory()->create(['node_id' => $server->node_id]);

        $allocation->server_id = $server->id;
        $allocation->save();

        Event::assertDispatched(
            AllocationsAssigned::class,
            fn (AllocationsAssigned $event) => $event->server->id === $server->id
                && in_array($allocation->id, $event->allocationIds, true),
        );
    }

    public function test_eloquent_save_with_server_id_cleared_fires_allocations_released(): void
    {
        $server = $this->createServerModel();
        $allocation = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);

        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $allocation->server_id = null;
        $allocation->save();

        Event::assertDispatched(
            AllocationsReleased::class,
            fn (AllocationsReleased $event) => $event->server->id === $server->id
                && in_array($allocation->id, $event->allocationIds, true),
        );
        Event::assertNotDispatched(AllocationsAssigned::class);
    }

    public function test_eloquent_create_with_server_id_set_fires_allocations_assigned(): void
    {
        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $server = $this->createServerModel();
        $allocation = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);

        Event::assertDispatched(
            AllocationsAssigned::class,
            fn (AllocationsAssigned $event) => $event->server->id === $server->id
                && in_array($allocation->id, $event->allocationIds, true),
        );
    }

    public function test_eloquent_create_without_server_id_fires_nothing(): void
    {
        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $server = $this->createServerModel();
        Allocation::factory()->create([
            'node_id' => $server->node_id,
            'server_id' => null,
        ]);

        Event::assertNotDispatched(AllocationsAssigned::class);
        Event::assertNotDispatched(AllocationsReleased::class);
    }

    public function test_eloquent_update_without_changing_server_id_fires_nothing(): void
    {
        $server = $this->createServerModel();
        $allocation = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);

        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $allocation->update(['notes' => 'something else']);

        Event::assertNotDispatched(AllocationsAssigned::class);
        Event::assertNotDispatched(AllocationsReleased::class);
    }

    public function test_server_deletion_fires_allocations_released_for_every_bound_row(): void
    {
        $server = $this->createServerModel();
        $bound = Allocation::factory()->times(3)->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);

        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        /** @var MockInterface $daemon */
        $daemon = $this->mock(DaemonServerRepository::class);
        $daemon->expects('setServer->delete')->andReturnUndefined();
        $databases = $this->mock(DatabaseManagementService::class);

        $service = new ServerDeletionService(
            $this->app->make(ConnectionInterface::class),
            $daemon,
            $databases,
        );

        $service->handle($server);

        Event::assertDispatched(
            AllocationsReleased::class,
            fn (AllocationsReleased $event) => count(array_intersect($event->allocationIds, $bound->pluck('id')->all())) === $bound->count(),
        );
    }

    public function test_build_modification_add_allocations_fires_allocations_assigned(): void
    {
        $server = $this->createServerModel();
        $free = Allocation::factory()->create(['node_id' => $server->node_id, 'server_id' => null]);

        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $daemon = $this->mock(DaemonServerRepository::class);
        $daemon->expects('setServer->sync')->andReturnUndefined();
        $service = $this->app->make(BuildModificationService::class);

        $service->handle($server, [
            'add_allocations' => [$free->id],
            'allocation_id' => $server->allocation_id,
        ]);

        Event::assertDispatched(
            AllocationsAssigned::class,
            fn (AllocationsAssigned $event) => $event->server->id === $server->id
                && in_array($free->id, $event->allocationIds, true),
        );
    }

    public function test_build_modification_remove_allocations_fires_allocations_released(): void
    {
        $server = $this->createServerModel();
        $extra = Allocation::factory()->create([
            'node_id' => $server->node_id,
            'server_id' => $server->id,
        ]);
        $server->refresh();

        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $daemon = $this->mock(DaemonServerRepository::class);
        $daemon->expects('setServer->sync')->andReturnUndefined();
        $service = $this->app->make(BuildModificationService::class);

        $service->handle($server, [
            'remove_allocations' => [$extra->id],
            'allocation_id' => $server->allocation_id,
        ]);

        Event::assertDispatched(
            AllocationsReleased::class,
            fn (AllocationsReleased $event) => $event->server->id === $server->id
                && in_array($extra->id, $event->allocationIds, true),
        );
    }

    public function test_events_implement_should_dispatch_after_commit(): void
    {
        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            class_implements(AllocationsAssigned::class),
        );
        $this->assertContains(
            ShouldDispatchAfterCommit::class,
            class_implements(AllocationsReleased::class),
        );
    }

    public function test_network_allocation_controller_delete_fires_allocations_released(): void
    {
        $server = $this->createServerModel();
        $server->update(['allocation_limit' => 2]);
        $allocation = Allocation::factory()->create([
            'server_id' => $server->id,
            'node_id' => $server->node_id,
        ]);

        Event::fake([AllocationsAssigned::class, AllocationsReleased::class]);

        $request = \Mockery::mock(DeleteAllocationRequest::class);
        $controller = $this->app->make(NetworkAllocationController::class);
        $controller->delete($request, $server, $allocation);

        Event::assertDispatched(
            AllocationsReleased::class,
            fn (AllocationsReleased $event) => $event->server->id === $server->id
                && in_array($allocation->id, $event->allocationIds, true),
        );
    }
}
