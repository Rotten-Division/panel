<?php

namespace App\Http\Middleware\Api\Client\Server;

use App\Enums\ServerState;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Models\Server;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AuthenticateServerAccess
{
    /**
     * Routes that this middleware should not apply to if the user is an admin.
     *
     * @var string[]
     */
    protected array $except = [
        'api:client:server.ws',
    ];

    /**
     * AuthenticateServerAccess constructor.
     */
    public function __construct() {}

    /**
     * Authenticate that this server exists and is not suspended or marked as installing.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var User $user */
        $user = $request->user();
        $server = $request->route()->parameter('server');

        if (!$server instanceof Server) {
            throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
        }

        // At the very least, ensure that the user trying to make this request is the
        // server owner, a subuser, or an admin. We'll leave it up to the controllers
        // to authenticate more detailed permissions if needed.
        if ($user->id !== $server->owner_id && $user->cannot('update server', $server)) {
            // Check for subuser status.
            if (!$server->subusers->contains('user_id', $user->id)) {
                throw new NotFoundHttpException(trans('exceptions.api.resource_not_found'));
            }
        }

        // nest evicted servers have no node and refuse every client api path
        // including the server.view introspection one. there is nothing for
        // wings to answer with while the volume is roosting on s3.
        if ($server->status === ServerState::Nest) {
            throw new ServerStateConflictException($server);
        }

        // hydrating means wings is mid restore, the server is taking shape
        // again on a node. let the dashboard poll server.view and
        // server.resources so the front end can watch progress, refuse
        // every other path because power and file access have nothing to
        // act on yet. short circuit past validateCurrentState below since
        // hydrating is a conflict state but we want these two routes to
        // flow through anyway.
        if ($server->status === ServerState::Hydrating) {
            if (!$request->routeIs('api:client:server.view')
                && !$request->routeIs('api:client:server.resources')) {
                throw new ServerStateConflictException($server);
            }

            $request->attributes->set('server', $server);

            return $next($request);
        }

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException $exception) {
            // Still allow users to get information about their server if it is installing or
            // being transferred.
            if (!$request->routeIs('api:client:server.view')) {
                if (($server->isSuspended() || $server->node?->isUnderMaintenance()) && !$request->routeIs('api:client:server.resources')) {
                    throw $exception;
                }
                if ($user->cannot('update server', $server) || !$request->routeIs($this->except)) {
                    throw $exception;
                }
            }
        }

        $request->attributes->set('server', $server);

        return $next($request);
    }
}
