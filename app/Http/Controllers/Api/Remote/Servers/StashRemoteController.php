<?php

namespace App\Http\Controllers\Api\Remote\Servers;

use App\Http\Controllers\Controller;
use App\Models\Server;
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
}
