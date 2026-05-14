<?php

namespace App\Http\Controllers\Api\Remote\Servers;

use App\Events\Server\AllocationsReleased;
use App\Exceptions\Http\HttpForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\TransferProgressCache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;
use Webmozart\Assert\Assert;

class ServerTransferController extends Controller
{
    /**
     * ServerTransferController constructor.
     */
    public function __construct(
        private ConnectionInterface $connection,
        private DaemonServerRepository $daemonServerRepository,
        private TransferProgressCache $transferProgressCache,
    ) {}

    /**
     * The daemon notifies us about a transfer failure.
     *
     * @throws Throwable
     */
    public function failure(Request $request, Server $server): JsonResponse
    {
        $transfer = $server->transfer;
        if (is_null($transfer)) {
            throw new ConflictHttpException('Server is not being transferred.');
        }

        /* @var Node $node */
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);

        // Either node can tell the panel that the transfer has failed. Only the new node
        // can tell the panel that it was successful.
        if (!$node->is($transfer->newNode) && !$node->is($transfer->oldNode)) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        $releasedOnFailure = [];
        $this->connection->transaction(function () use ($transfer, &$releasedOnFailure) {
            $transfer->forceFill(['successful' => false])->saveOrFail();

            if ($transfer->new_allocation || $transfer->new_additional_allocations) {
                $allocations = array_merge([$transfer->new_allocation], $transfer->new_additional_allocations);
                Allocation::query()->whereIn('id', $allocations)->update(['server_id' => null]);
                $releasedOnFailure = $allocations;
            }
        });

        if ($releasedOnFailure !== []) {
            event(new AllocationsReleased($server, $releasedOnFailure));
        }

        $this->transferProgressCache->forget($server);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * The daemon notifies us about a transfer success.
     *
     * @throws Throwable
     */
    public function success(Request $request, Server $server): JsonResponse
    {
        $transfer = $server->transfer;
        if (is_null($transfer)) {
            throw new ConflictHttpException('Server is not being transferred.');
        }

        /* @var Node $node */
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);

        // Only the new node communicates a successful state to the panel, so we should
        // not allow the old node to hit this endpoint.
        if (!$node->is($transfer->newNode)) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        $releasedOldAllocations = [];

        /** @var Server $server */
        $server = $this->connection->transaction(function () use ($server, $transfer, &$releasedOldAllocations) {
            $data = [];

            if ($transfer->old_allocation || $transfer->old_additional_allocations) {
                $allocations = array_merge([$transfer->old_allocation], $transfer->old_additional_allocations);
                // Remove the old allocations for the server and re-assign the server to the new
                // primary allocation and node.
                Allocation::query()->whereIn('id', $allocations)->update(['server_id' => null]);
                $releasedOldAllocations = $allocations;
                $data['allocation_id'] = $transfer->new_allocation;
            }

            $data['node_id'] = $transfer->new_node;
            $server->update($data);

            $server = $server->fresh();
            $server->transfer->update(['successful' => true]);

            return $server;
        });

        if ($releasedOldAllocations !== []) {
            event(new AllocationsReleased($server, $releasedOldAllocations));
        }

        // Delete the server from the old node making sure to point it to the old node so
        // that we do not delete it from the new node the server was transferred to.
        try {
            $this->daemonServerRepository
                ->setServer($server)
                ->setNode($transfer->oldNode)
                ->delete();
        } catch (ConnectionException $exception) {
            logger()->warning($exception, ['transfer_id' => $server->transfer->id]);
        }

        $this->transferProgressCache->forget($server);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }

    /**
     * The daemon reports a structured progress update mid-transfer.
     * Wings fires this at ~500ms cadence during the source upload and
     * once per step transition on the destination. Either node may
     * post — auth matches the failure/success handlers.
     */
    public function progress(Request $request, Server $server): JsonResponse
    {
        $transfer = $server->transfer;
        if (is_null($transfer)) {
            throw new ConflictHttpException('Server is not being transferred.');
        }

        /* @var Node $node */
        Assert::isInstanceOf($node = $request->attributes->get('node'), Node::class);

        if (!$node->is($transfer->newNode) && !$node->is($transfer->oldNode)) {
            throw new HttpForbiddenException('Requesting node does not have permission to access this server.');
        }

        $payload = $request->validate([
            'step' => ['required', 'string', 'in:archiving,uploading,extracting,verifying,cleanup'],
            'bytes' => ['required', 'integer', 'min:0'],
            'total_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $this->transferProgressCache->put($server, $payload);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
