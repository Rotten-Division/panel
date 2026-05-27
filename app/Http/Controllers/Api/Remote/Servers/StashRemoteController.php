<?php

namespace App\Http\Controllers\Api\Remote\Servers;

use App\Exceptions\Http\HttpForbiddenException;
use App\Http\Controllers\Controller;
use App\Models\Node;
use App\Models\Server;
use App\Services\Servers\RetrieveProgressCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// stub for the wings stash callback routes. wings posts to /captured after a
// successful stash upload and /restored after a successful retrieval. the
// stash manager plugin rebinds the controller in its provider during phase D
// to actually act on the callback. without the plugin installed the routes
// answer 503 so wings can log a clear failure and the orphan sweep cleans up.
// the URL paths retain the legacy /nest prefix so wings does not need to
// learn a new wire format and existing signed warning-email URLs stay live.
class StashRemoteController extends Controller
{
    public function captured(Request $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'error' => 'stash manager plugin is not installed',
        ], 503);
    }

    public function restored(Request $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'error' => 'stash manager plugin is not installed',
        ], 503);
    }

    public function progress(Request $request, Server $server, RetrieveProgressCache $cache): JsonResponse
    {
        // the retrieve destination is the server's current node during
        // Retrieving; only that node may report progress for it.
        $node = $request->attributes->get('node');
        if ($server->node_id !== null && (!$node instanceof Node || $node->id !== $server->node_id)) {
            throw new HttpForbiddenException('Requesting node does not own this server.');
        }

        $payload = $request->validate([
            'step' => ['required', 'string', 'in:downloading,extracting'],
            'bytes' => ['required', 'integer', 'min:0'],
            'total_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $cache->mergeProgress($server, $payload);

        return new JsonResponse([], 204);
    }
}
