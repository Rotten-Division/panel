<?php

namespace App\Exceptions\Servers;

use App\Exceptions\DisplayException;
use Throwable;

/**
 * thrown when a port could not be claimed because another server already owns
 * it somewhere in the fleet. it is a distinct type so a bind-path retry (the
 * server wizard) can catch exactly this and reselect a fresh free port, without
 * also swallowing a wings/daemon failure. it extends DisplayException so the
 * client api and Network tab surface a clean "port taken, try again" rather than
 * a 500.
 */
class PortClaimConflictException extends DisplayException
{
    public function __construct(string $message = 'That port was just claimed by another server, please try again.', ?Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
