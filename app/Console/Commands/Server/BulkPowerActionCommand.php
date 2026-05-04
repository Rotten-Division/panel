<?php

namespace App\Console\Commands\Server;

use App\Contracts\Servers\ServerStartGate;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\StartGateDecision;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Factory as ValidatorFactory;
use Illuminate\Validation\ValidationException;

class BulkPowerActionCommand extends Command
{
    protected $signature = 'p:server:bulk-power
                            {action : The action to perform (start, stop, restart, kill)}
                            {--servers= : A comma separated list of servers.}
                            {--nodes= : A comma separated list of nodes.}
                            {--bypass-policy : Skip the running server policy gate, use only when the operator explicitly intends to override the per owner one running policy.}';

    protected $description = 'Perform bulk power management on large groupings of servers or nodes at once.';

    public function handle(DaemonServerRepository $serverRepository, ValidatorFactory $validator, ServerStartGate $startGate): void
    {
        $action = $this->argument('action');
        $nodes = empty($this->option('nodes')) ? [] : explode(',', $this->option('nodes'));
        $servers = empty($this->option('servers')) ? [] : explode(',', $this->option('servers'));
        $bypassPolicy = (bool) $this->option('bypass-policy');

        $validator = $validator->make([
            'action' => $action,
            'nodes' => $nodes,
            'servers' => $servers,
        ], [
            'action' => 'string|in:start,stop,kill,restart',
            'nodes' => 'array',
            'nodes.*' => 'integer|min:1',
            'servers' => 'array',
            'servers.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            foreach ($validator->getMessageBag()->all() as $message) {
                $this->output->error($message);
            }

            throw new ValidationException($validator);
        }

        $count = $this->getQueryBuilder($servers, $nodes)->count();
        if (!$this->confirm(trans('command/messages.server.power.confirm', ['action' => $action, 'count' => $count])) && $this->input->isInteractive()) {
            return;
        }

        $bar = $this->output->createProgressBar($count);

        // collect outcomes during the loop and print one summary at the end,
        // mid loop warnings interrupt the progress bar and scroll out of view
        // on a long batch.
        $skipped = [];
        $swapped = [];
        $failed = [];

        $this->getQueryBuilder($servers, $nodes)->get()->each(function ($server, int $index) use ($action, $serverRepository, $startGate, $bypassPolicy, &$bar, &$skipped, &$swapped, &$failed): mixed {
            if (!$server instanceof Server) {
                return null;
            }

            try {
                // route start through the gate by default so the per owner one
                // running policy applies to bulk operations the same way it
                // applies to ui and api starts. operators that intend to override
                // the policy pass --bypass-policy.
                if ($action === 'start' && !$bypassPolicy) {
                    $decision = $startGate->gateStart(
                        $server,
                        $server->user,
                        fn () => $serverRepository->setServer($server)->power('start'),
                    );

                    if (!$decision->proceeded) {
                        $skipped[] = [
                            'id' => (string) $server->id,
                            'name' => $server->name,
                            'reason' => $decision->outcome,
                            'detail' => (string) $decision->message,
                        ];
                    } elseif ($decision->outcome === StartGateDecision::SWAPPED && $decision->stopped !== null) {
                        $swapped[] = [
                            'id' => (string) $server->id,
                            'name' => $server->name,
                            'stopped' => $decision->stopped->name,
                        ];
                    }
                } else {
                    $serverRepository->setServer($server)->power($action);
                }
            } catch (Exception $exception) {
                $failed[] = [
                    'id' => (string) $server->id,
                    'name' => $server->name,
                    'node' => $server->node->name,
                    'message' => $exception->getMessage(),
                ];
            }

            $bar->advance();

            return null;
        });

        $bar->finish();
        $this->line('');
        $this->newLine();

        if ($swapped !== []) {
            $this->info(count($swapped) . ' swap(s) happened to honour the one running server policy.');
            $this->table(
                ['ID', 'Started', 'Stopped'],
                array_map(fn (array $row) => [$row['id'], $row['name'], $row['stopped']], $swapped),
            );
        }

        if ($skipped !== []) {
            $this->warn(count($skipped) . ' server(s) skipped by the start gate.');
            $this->table(
                ['ID', 'Name', 'Reason', 'Detail'],
                array_map(fn (array $row) => [$row['id'], $row['name'], $row['reason'], $row['detail']], $skipped),
            );
        }

        if ($failed !== []) {
            $this->error(count($failed) . ' server(s) failed.');
            $this->table(
                ['ID', 'Name', 'Node', 'Error'],
                array_map(fn (array $row) => [$row['id'], $row['name'], $row['node'], $row['message']], $failed),
            );
        }
    }

    /**
     * Returns the query builder instance that will return the servers that should be affected.
     *
     * @param  string[]|int[]  $servers
     * @param  string[]|int[]  $nodes
     */
    protected function getQueryBuilder(array $servers, array $nodes): Builder
    {
        $instance = Server::query()->whereNull('status');

        if (!empty($nodes) && !empty($servers)) {
            $instance->whereIn('id', $servers)->orWhereIn('node_id', $nodes);
        } elseif (empty($nodes) && !empty($servers)) {
            $instance->whereIn('id', $servers);
        } elseif (!empty($nodes)) {
            $instance->whereIn('node_id', $nodes);
        }

        return $instance->with('node');
    }
}
