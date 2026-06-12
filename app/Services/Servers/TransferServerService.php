<?php

namespace App\Services\Servers;

use App\Contracts\Servers\NodeRoutableGate;
use App\Events\Server\AllocationsAssigned;
use App\Exceptions\DisplayException;
use App\Exceptions\Servers\PortClaimConflictException;
use App\Models\Allocation;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerTransfer;
use App\Services\Nodes\NodeJWTService;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\UnencryptedToken;
use Throwable;

class TransferServerService
{
    /**
     * TransferService constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private NodeJWTService $nodeJWTService,
        private NodeRoutableGate $nodeRoutableGate,
        private PortClaim $portClaim,
    ) {}

    /**
     * @param  string[]  $backup_uuids
     */
    private function notify(ServerTransfer $transfer, UnencryptedToken $token, array $backup_uuids = []): void
    {
        $backups = [];
        if (config('backups.default') === Backup::ADAPTER_DAEMON) {
            $backups = $backup_uuids;
        }
        Http::daemon($transfer->oldNode)->post("/api/servers/{$transfer->server->uuid}/transfer", [
            'url' => $transfer->newNode->getConnectionAddress() . '/api/transfers',
            'token' => 'Bearer ' . $token->toString(),
            'backups' => $backups,
            'server' => [
                'uuid' => $transfer->server->uuid,
                'start_on_completion' => false,
            ],
        ]);
    }

    /**
     * Starts a transfer of a server to a new node.
     *
     * @param  int[]  $additional_allocations
     * @param  string[]  $backup_uuid
     *
     * @throws Throwable
     */
    public function handle(Server $server, int $node_id, ?int $allocation_id = null, ?array $additional_allocations = [], ?array $backup_uuid = []): bool
    {
        $additional_allocations = array_map(intval(...), $additional_allocations);

        // Check if the node is viable for the transfer.
        $node = Node::query()
            ->select(['nodes.id', 'nodes.fqdn', 'nodes.scheme', 'nodes.daemon_token', 'nodes.daemon_connect', 'nodes.memory', 'nodes.disk', 'nodes.cpu', 'nodes.memory_overallocate', 'nodes.disk_overallocate', 'nodes.cpu_overallocate'])
            ->withSum('servers', 'disk')
            ->withSum('servers', 'memory')
            ->withSum('servers', 'cpu')
            ->leftJoin('servers', 'servers.node_id', '=', 'nodes.id')
            ->where('nodes.id', $node_id)
            ->first();

        if (!$node->isViable($server->memory, $server->disk, $server->cpu)) {
            return false;
        }

        $server->validateTransferState();

        /** @var ServerTransfer $transfer */
        $transfer = $this->connection->transaction(function () use ($server, $node_id, $allocation_id, $additional_allocations) {
            // Create a new ServerTransfer entry.
            $transfer = ServerTransfer::create([
                'server_id' => $server->id,
                'old_node' => $server->node_id,
                'new_node' => $node_id,
            ]);

            if ($server->allocation_id) {
                $transfer->old_allocation = $server->allocation_id;
                $transfer->new_allocation = $allocation_id;
                $transfer->old_additional_allocations = $server->allocations->where('id', '!=', $server->allocation_id)->pluck('id')->all();
                $transfer->new_additional_allocations = $additional_allocations;

                // Add the allocations to the server, so they cannot be automatically assigned while the transfer is in progress.
                $this->assignAllocationsToServer($server, $node_id, $allocation_id, $additional_allocations);
            }

            $transfer->save();

            return $transfer;
        });

        // Generate a token for the destination node that the source node can use to authenticate with.
        $token = $this->nodeJWTService
            ->setExpiresAt(CarbonImmutable::now()->addMinutes(15))
            ->setSubject($server->uuid)
            ->handle($transfer->newNode, $server->uuid, 'sha256');

        // Notify the source node of the pending outgoing transfer.
        $this->notify($transfer, $token, $backup_uuid);

        return true;
    }

    /**
     * Assigns the specified allocations to the specified server.
     *
     * @param  int[]  $additional_allocations
     */
    private function assignAllocationsToServer(Server $server, int $node_id, int $allocation_id, array $additional_allocations): void
    {
        $allocations = $additional_allocations;
        $allocations[] = $allocation_id;

        $node = Node::findOrFail($node_id);
        $unassigned = $node->allocations()
            ->whereNull('server_id')
            ->pluck('id')
            ->toArray();

        $updateIds = [];
        foreach ($allocations as $allocation) {
            if (!in_array($allocation, $unassigned)) {
                continue;
            }

            $updateIds[] = $allocation;
        }

        if (empty($updateIds)) {
            return;
        }

        // the destination ports already belong to this server on the source node
        // (port-follows-server), so the destination cannot assert for(port)===Free (it
        // is Bound on the source mid-move). the precondition is bound-by-self on the
        // source plus a free destination row.
        $ports = Allocation::query()->whereIn('id', $updateIds)->pluck('port')->map(intval(...))->all();

        $this->portClaim->withClaims($ports, function () use ($server, $node_id, $updateIds) {
            if (!$this->nodeRoutableGate->routable($node_id)) {
                throw new DisplayException('The destination node has no routing peer and cannot host this server.');
            }

            // re-read under the FOR UPDATE lock: a pre-lock snapshot can be stale if
            // another claimant bound a destination row while this transfer waited.
            $destinations = Allocation::query()->whereIn('id', $updateIds)->get();
            foreach ($destinations as $destination) {
                if ($destination->server_id !== null) {
                    throw new DisplayException("Destination allocation {$destination->id} is no longer free.");
                }

                // a source allocation on this port must currently be bound to the
                // transferring server: this is the transfer's bound-by-self proof.
                $sourceBound = Allocation::query()
                    ->where('port', $destination->port)
                    ->where('server_id', $server->id)
                    ->exists();
                if (!$sourceBound) {
                    throw new DisplayException("Port {$destination->port} is not bound to this server on the source node.");
                }
            }

            // conditional flip is the atomic backstop: only rows still free are bound,
            // and every destination must bind or the whole transfer aborts.
            $bound = Allocation::query()->whereIn('id', $updateIds)->whereNull('server_id')->update(['server_id' => $server->id]);
            if ($bound !== count($updateIds)) {
                throw new PortClaimConflictException();
            }
            event(new AllocationsAssigned($server, $updateIds));
        });
    }
}
