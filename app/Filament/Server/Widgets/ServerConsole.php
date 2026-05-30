<?php

namespace App\Filament\Server\Widgets;

use App\Enums\SubuserPermission;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Session;

/**
 * the public typed properties below are hydrated by filament's widget
 * container from the `:data` array on `<x-filament-widgets::widgets>`. state
 * partials pass overrides like `:data="$this->getWidgetData() + ['readOnly' => true]"`.
 * the keys map 1:1 to property names, not routed through a `mount()` method
 * because filament + livewire hydrate public properties directly. if you add a
 * `mount()` here, include `bool $readOnly = false` so the data array keeps flowing.
 */
class ServerConsole extends Widget
{
    protected string $view = 'filament.components.server-console';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    public ?User $user = null;

    /** hides the command-input row. reactive so it tracks the overview's live status. */
    #[Reactive]
    public bool $readOnly = false;

    /** @var string[] */
    #[Session(key: 'server.{server.id}.history')]
    public array $history = [];

    public int $historyIndex = 0;

    public string $input = '';

    protected function authorizeSendCommand(): bool
    {
        return $this->user->can(SubuserPermission::ControlConsole, $this->server);
    }

    protected function canSendCommand(): bool
    {
        return $this->authorizeSendCommand() && !$this->server->isInConflictState() && $this->server->retrieveStatus()->isStartingOrRunning();
    }

    public function up(): void
    {
        $this->historyIndex = min($this->historyIndex + 1, count($this->history) - 1);

        $this->input = $this->history[$this->historyIndex] ?? '';
    }

    public function down(): void
    {
        $this->historyIndex = max($this->historyIndex - 1, -1);

        $this->input = $this->history[$this->historyIndex] ?? '';
    }

    public function enter(): void
    {
        if (!empty($this->input) && $this->canSendCommand()) {
            $this->js('window.OspiteConsole.send('.json_encode($this->server->uuid).', '.json_encode($this->input).')');

            $this->history = Arr::prepend($this->history, $this->input);
            $this->historyIndex = -1;

            $this->input = '';
        }
    }

    #[On('store-stats')]
    public function storeStats(string $data): void
    {
        $data = json_decode($data);

        $timestamp = now()->getTimestamp();

        foreach ($data as $key => $value) {
            $cacheKey = "servers.{$this->server->id}.$key";
            $cachedStats = cache()->get($cacheKey, []);

            $cachedStats[$timestamp] = $value;

            // preserve_keys is critical. the entries are keyed by unix
            // timestamp, and array_slice without it reindexes integer keys to
            // 0..N, replacing the timestamps with sample indexes and breaking
            // every consumer that reads array_keys() to recover sample times.
            cache()->put($cacheKey, array_slice($cachedStats, -120, null, preserve_keys: true), now()->addMinute());
        }
    }

    #[On('websocket-error')]
    public function websocketError(): void
    {
        // suppress the websocket failure banner whenever the server is in a
        // conflict state (install failed, transferring, suspended, stashed).
        // wings has nothing to serve over the socket in those cases and the
        // state partial already tells the user what's going on. raising a
        // second red banner on top of the inline state copy is noise.
        /** @var Server $server */
        $server = Filament::getTenant();
        if ($server->isInConflictState()) {
            return;
        }

        AlertBanner::make('websocket_error')
            ->title(trans('server/console.websocket_error.title'))
            ->body(trans('server/console.websocket_error.body'))
            ->danger()
            ->send();
    }
}
