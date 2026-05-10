<?php

namespace App\Http\Controllers\Api\Remote\Servers;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// stub for the wings nest callback routes. wings posts to /captured after a
// successful capture upload and /restored after a successful restore. the
// nest manager plugin rebinds the controller in its provider during phase D
// to actually act on the callback. without the plugin installed the routes
// answer 503 so wings can log a clear failure and the orphan sweep cleans up.
class NestRemoteController extends Controller
{
    public function captured(Request $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'error' => 'nest plugin is not installed',
        ], 503);
    }

    public function restored(Request $request, Server $server): JsonResponse
    {
        return new JsonResponse([
            'error' => 'nest plugin is not installed',
        ], 503);
    }
}
