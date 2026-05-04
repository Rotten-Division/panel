<?php

namespace App\Services\Servers;

use App\Models\Server;

/**
 * outcome of a ServerStartGate gateStart call. proceeded reflects whether the
 * gate invoked the perform closure, outcome is a stable string for logging
 * and api responses, message is a user facing line for surfaces that render
 * notifications.
 */
final class StartGateDecision
{
    public const ALLOWED = 'allowed';

    public const SWAPPED = 'swapped';

    public const LOCK_TIMEOUT = 'lock_timeout';

    public const PERMISSION_DENIED = 'permission_denied';

    public const STOP_FAILED = 'stop_failed';

    public function __construct(
        public readonly bool $proceeded,
        public readonly string $outcome,
        public readonly ?Server $stopped = null,
        public readonly ?string $message = null,
    ) {}

    public static function allowed(): self
    {
        return new self(true, self::ALLOWED);
    }

    public static function swapped(Server $stopped): self
    {
        return new self(true, self::SWAPPED, $stopped);
    }

    public static function lockTimeout(): self
    {
        return new self(
            false,
            self::LOCK_TIMEOUT,
            null,
            'Another start is already in flight for your account, try again in a moment.',
        );
    }

    public static function permissionDenied(): self
    {
        return new self(
            false,
            self::PERMISSION_DENIED,
            null,
            'Another server is already running for this account. Only one server can run at a time, ask the owner to stop the other one before starting this one.',
        );
    }

    public static function stopFailed(Server $blocker): self
    {
        return new self(
            false,
            self::STOP_FAILED,
            $blocker,
            "Could not confirm \"{$blocker->name}\" stopped, the start was aborted to avoid two servers running at once.",
        );
    }

    /**
     * http status that best reflects the outcome for api responses, 204 on
     * success, 423 for the transient lock conflict, 403 for the permission
     * gate, 502 for a stop that wings either rejected or did not confirm
     * since the panel is acting as a gateway to wings on this path.
     */
    public function httpStatus(): int
    {
        return match ($this->outcome) {
            self::ALLOWED, self::SWAPPED => 204,
            self::LOCK_TIMEOUT => 423,
            self::PERMISSION_DENIED => 403,
            self::STOP_FAILED => 502,
            default => 500,
        };
    }
}
