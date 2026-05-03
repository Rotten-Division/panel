<?php

namespace App\Exceptions\Servers;

use App\Services\Servers\StartGateDecision;
use RuntimeException;
use Throwable;

/**
 * thrown when a ServerStartGate refuses a start. carries the gate decision
 * so callers can branch on the outcome code, scheduled task runners can
 * decide whether the schedules continue on failure flag should absorb it,
 * and api controllers can surface the right http status and message.
 */
class StartGateRefusedException extends RuntimeException
{
    public function __construct(public readonly StartGateDecision $decision, ?Throwable $previous = null)
    {
        parent::__construct(
            $decision->message ?? "start gate refused, {$decision->outcome}",
            previous: $previous,
        );
    }
}
