<?php

namespace App\View\Components\Overview;

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Models\Server;
use Illuminate\View\Component;

class PowerButtons extends Component
{
    public function __construct(
        public Server $server,
        public ?ContainerStatus $containerStatus = null,
    ) {}

    public function shouldHide(): bool
    {
        // transferring is detected via the relation, no ServerState case exists.
        if ($this->server->transfer !== null && $this->server->transfer->successful === null) {
            return true;
        }

        return in_array($this->server->status, [
            ServerState::Nest,
            ServerState::Hydrating,
            ServerState::Capturing,
            ServerState::Installing,
            ServerState::InstallFailed,
            ServerState::ReinstallFailed,
            ServerState::RestoringBackup,
            ServerState::Suspended,
        ], true);
    }

    public function canStart(): bool
    {
        return $this->containerStatus === ContainerStatus::Offline;
    }

    public function canRestart(): bool
    {
        return $this->containerStatus === ContainerStatus::Running;
    }

    public function canStop(): bool
    {
        return in_array($this->containerStatus, [
            ContainerStatus::Running,
            ContainerStatus::Starting,
        ], true);
    }

    public function render()
    {
        return view('components.overview.power-buttons');
    }
}
