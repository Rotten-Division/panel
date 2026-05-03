<?php

namespace App\Extensions\Tasks\Schemas;

use App\Contracts\Servers\ServerStartGate;
use App\Models\Task;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;
use RuntimeException;

final class PowerActionSchema extends TaskSchema
{
    public function __construct(
        private DaemonServerRepository $serverRepository,
        private ServerStartGate $startGate,
    ) {}

    public function getId(): string
    {
        return 'power';
    }

    public function runTask(Task $task): void
    {
        $server = $task->server;

        // route scheduled starts through the panels start gate so the one
        // running server policy applies to schedules just like the ui. on a
        // gate refusal the task throws so the schedules continue on failure
        // flag governs whether subsequent tasks still run.
        if ($task->payload === 'start') {
            $decision = $this->startGate->gateStart(
                $server,
                $server->user,
                fn () => $this->serverRepository->setServer($server)->power('start'),
            );

            if (!$decision->proceeded) {
                throw new RuntimeException("scheduled start blocked, {$decision->outcome}");
            }

            return;
        }

        $this->serverRepository->setServer($server)->power($task->payload);
    }

    public function getDefaultPayload(): string
    {
        return 'restart';
    }

    public function getPayloadLabel(): string
    {
        return trans('server/schedule.tasks.actions.power.action');
    }

    public function formatPayload(string $payload): string
    {
        return Str::ucfirst($payload);
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [
            Select::make('payload')
                ->label($this->getPayloadLabel())
                ->required()
                ->options([
                    'start' => trans('server/schedule.tasks.actions.power.start'),
                    'restart' => trans('server/schedule.tasks.actions.power.restart'),
                    'stop' => trans('server/schedule.tasks.actions.power.stop'),
                    'kill' => trans('server/schedule.tasks.actions.power.kill'),
                ])
                ->selectablePlaceholder(false)
                ->default($this->getDefaultPayload()),
        ];
    }
}
