<?php

namespace App\View\Components\Overview;

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use Illuminate\View\Component;

class StatePill extends Component
{
    public function __construct(
        public ?ServerState $state = null,
        public ?ContainerStatus $containerStatus = null,
        public bool $transferring = false,
    ) {}

    public function variant(): string
    {
        // transferring is a relation flag, not an enum state. the dispatcher
        // passes it in because no ServerState::Transferring case exists.
        if ($this->transferring) {
            return 'transient';
        }

        if ($this->state instanceof ServerState) {
            return match ($this->state) {
                ServerState::Suspended,
                ServerState::InstallFailed,
                ServerState::ReinstallFailed => 'suspended',
                ServerState::Installing,
                ServerState::RestoringBackup => 'installing',
                ServerState::Stashed,
                ServerState::Retrieving,
                ServerState::Stashing => 'stashed',
            };
        }

        // null state, container status disambiguates between online,
        // transient, and stopped.
        return match ($this->containerStatus) {
            ContainerStatus::Running => 'online',
            ContainerStatus::Starting,
            ContainerStatus::Stopping,
            ContainerStatus::Restarting => 'transient',
            default => 'stopped',
        };
    }

    public function label(): string
    {
        if ($this->transferring) {
            return 'TRANSFERRING';
        }
        if ($this->state instanceof ServerState) {
            return strtoupper(str_replace('_', ' ', $this->state->value));
        }

        return strtoupper($this->containerStatus?->value ?? 'unknown');
    }

    public function pulses(): bool
    {
        return in_array($this->variant(), ['transient', 'installing'], true);
    }

    public function render()
    {
        return view('components.overview.state-pill', [
            'variant' => $this->variant(),
            'label' => $this->label(),
            'pulses' => $this->pulses(),
        ]);
    }
}
