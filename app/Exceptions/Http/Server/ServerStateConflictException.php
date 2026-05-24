<?php

namespace App\Exceptions\Http\Server;

use App\Enums\ServerState;
use App\Models\Server;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ServerStateConflictException extends ConflictHttpException
{
    /**
     * Exception thrown when the server is in an unsupported state for API access or
     * certain operations within the codebase.
     */
    public function __construct(Server $server, ?Throwable $previous = null)
    {
        $message = 'This server is currently in an unsupported state, please try again later.';
        if ($server->status === ServerState::Stashed) {
            $message = 'This server is in cold storage. Hit wake to bring it back.';
        } elseif ($server->status === ServerState::Retrieving) {
            $message = 'This server is being retrieved from cold storage. Hold tight, this takes about a minute.';
        } elseif ($server->status === ServerState::Stashing) {
            $message = 'This server is being moved to cold storage. Hold tight, this takes about a minute.';
        } elseif ($server->isSuspended()) {
            $message = 'This server is currently suspended and the functionality requested is unavailable.';
        } elseif ($server->node?->isUnderMaintenance()) {
            $message = 'The node of this server is currently under maintenance and the functionality requested is unavailable.';
        } elseif (!$server->isInstalled()) {
            $message = 'This server has not yet completed its installation process, please try again later.';
        } elseif ($server->status === ServerState::RestoringBackup) {
            $message = 'This server is currently restoring from a backup, please try again later.';
        } elseif (!is_null($server->transfer)) {
            $message = 'This server is currently being transferred to a new machine, please try again later.';
        }

        parent::__construct($message, $previous);
    }
}
