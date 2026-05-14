<?php

namespace App\Filament\Server\Pages;

use App\Contracts\Servers\PlayerCountProvider;
use App\Contracts\Servers\ServerStartGate;
use App\Enums\ConsoleWidgetPosition;
use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Extensions\Features\FeatureService;
use App\Filament\Components\Actions\StartSwapModal;
use App\Filament\Server\Widgets\ServerConsole;
use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use App\Filament\Server\Widgets\ServerOverview;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Services\Servers\StartGateDecision;
use App\Traits\Filament\CanCustomizeHeaderActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Filament\Support\Enums\Size;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\App;
use Livewire\Attributes\On;

class Overview extends Page
{
    use CanCustomizeHeaderActions, HasHeaderActions {
        CanCustomizeHeaderActions::getHeaderActions insteadof HasHeaderActions;
    }
    use InteractsWithActions;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::LayoutDashboard;

    protected string $view = 'filament.server.pages.overview';

    public ContainerStatus $status = ContainerStatus::Offline;

    public ?int $playerCount = null;

    public ?int $playerLimit = null;

    public int $diskUsedBytes = 0;

    protected FeatureService $featureService;

    // replaces the default Filament page header (breadcrumb + title) with
    // a game / flavour / version eyebrow row and a slot the Phase 4
    // components mount into. Filament's page template calls getHeader().
    // Passes the existing header actions through so the power buttons
    // stay live until Phase 4 lands the dedicated PowerButtons component.
    public function getHeader(): ?ViewContract
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return view('filament.server.pages.overview-header', [
            'server' => $server,
            'eyebrow' => [
                'game' => $this->displayGame($server->game),
                'flavour' => $server->flavour,
                'version' => $server->version,
            ],
            'state' => $server->status,
            'containerStatus' => $this->status,
            'transferActive' => $server->transfer !== null && $server->transfer->successful === null,
        ]);
    }

    // bedrock carries game:bedrock for routing pool separation, but the
    // eyebrow groups it under minecraft because bedrock is a flavour of
    // minecraft in the user's mental model, not a separate game.
    private function displayGame(?string $game): ?string
    {
        if ($game === null) {
            return null;
        }

        return match ($game) {
            'minecraft', 'bedrock' => 'minecraft',
            default => $game,
        };
    }

    public function mount(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException $exception) {
            // skip the session banner for any nest-related state. the nest
            // manager plugin renders an inline NestNotice widget at the top
            // of the page that carries the appropriate brand-voice copy and
            // the wake button. AlertBanner stays the path for the other
            // conflict reasons (installing, transferring, suspended).
            if (in_array($server->status, [ServerState::Nest, ServerState::Hydrating, ServerState::Capturing], true)) {
                return;
            }

            AlertBanner::make('server_conflict')
                ->title('Warning')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }

    public function boot(FeatureService $featureService): void
    {
        $this->featureService = $featureService;
        /** @var Server $server */
        $server = Filament::getTenant();
        foreach ($featureService->getActiveSchemas($server->egg->features) as $feature) {
            $this->cacheAction($feature->getAction());
        }
    }

    #[On('mount-feature')]
    public function mountFeature(string $data): void
    {
        $data = json_decode($data);
        $feature = data_get($data, 'key');

        $feature = $this->featureService->get($feature);
        if (!$feature) {
            return;
        }

        if ($this->getMountedAction()) {
            $this->replaceMountedAction($feature->getId());
        } else {
            $this->mountAction($feature->getId());
        }
    }

    public function getWidgetData(): array
    {
        return [
            'server' => Filament::getTenant(),
            'user' => user(),
        ];
    }

    /** @var array<string, array<class-string<Widget>>> */
    protected static array $customWidgets = [];

    /** @param class-string<Widget>[] $customWidgets */
    public static function registerCustomWidgets(ConsoleWidgetPosition $position, array $customWidgets): void
    {
        static::$customWidgets[$position->value] = array_unique(array_merge(static::$customWidgets[$position->value] ?? [], $customWidgets));
    }

    /**
     * @return class-string<Widget>[]
     */
    public function getWidgets(): array
    {
        // nest evicted servers have node_id=null so the panel-core widgets
        // (ServerOverview, ServerConsole, *Chart) crash on $server->node->X.
        // Hydrating and Capturing servers do have a node but wings has
        // nothing to serve through the websocket while the volume transfer
        // is in flight, the console widget surfaces a websocket connect
        // failure that obscures the NestNotice's progress card. for all
        // three nest-related states, return only the Top-slot plugin
        // widgets, which is where the nest manager plugin registers
        // NestNotice. AboveConsole / BelowConsole / Bottom plugin widgets
        // are skipped because they typically depend on a live wings node.
        //
        // a server with no node assignment also gets the short-circuit
        // regardless of status. that state is invalid in normal flow but
        // can be hit transiently if a force-evict failed mid-rollback or
        // an admin nulls the column manually. without this guard the
        // strings plugin's overridden console blade would 500 on
        // $server->node->getConnectionAddress().
        /** @var Server $server */
        $server = Filament::getTenant();
        if (
            $server->node_id === null
            || in_array($server->status, [ServerState::Nest, ServerState::Hydrating, ServerState::Capturing], true)
        ) {
            return static::$customWidgets[ConsoleWidgetPosition::Top->value] ?? [];
        }

        $allWidgets = [];

        $allWidgets = array_merge($allWidgets, static::$customWidgets[ConsoleWidgetPosition::Top->value] ?? []);

        $allWidgets[] = ServerOverview::class;

        $allWidgets = array_merge($allWidgets, static::$customWidgets[ConsoleWidgetPosition::AboveConsole->value] ?? []);

        $allWidgets[] = ServerConsole::class;

        $allWidgets = array_merge($allWidgets, static::$customWidgets[ConsoleWidgetPosition::BelowConsole->value] ?? []);

        $allWidgets = array_merge($allWidgets, [
            ServerCpuChart::class,
            ServerMemoryChart::class,
            ServerNetworkChart::class,
        ]);

        $allWidgets = array_merge($allWidgets, static::$customWidgets[ConsoleWidgetPosition::Bottom->value] ?? []);

        return array_unique($allWidgets);
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    public function getColumns(): int
    {
        return 3;
    }

    #[On('console-status')]
    public function receivedConsoleUpdate(?string $state = null): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        if ($state) {
            $this->status = ContainerStatus::from($state);
            cache()->put("servers.$server->uuid.status", $this->status, now()->addSeconds(15));
        }

        $this->refreshLiveData();
        $this->headerActions($this->getHeaderActions());
    }

    /**
     * resolve a plugin state handler that owns the entire body render
     * for the current server. phase 7 nest manager registers a handler
     * here; until then the contract returns null so the dispatcher
     * falls through to the built-in state switch.
     */
    public function resolveStateHandler(Server $server): ?object
    {
        return null;
    }

    public function refreshLiveData(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        // disk usage from wings stats cache, populated by the console
        // websocket as stats events stream in.
        $resources = cache()->get("servers.$server->uuid.resources");
        $this->diskUsedBytes = (int) ($resources['disk_bytes'] ?? 0);

        // player count contract returns null by default; live stats plugin
        // would rebind to return real values.
        $payload = App::make(PlayerCountProvider::class)->resolve($server);
        $this->playerCount = $payload['current'] ?? null;
        $this->playerLimit = $payload['max'] ?? null;
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            ActionGroup::make([
                StartSwapModal::configure(
                    Action::make('start')
                        ->label(trans('server/console.power_actions.start'))
                        ->color('primary')
                        ->icon(TablerIcon::PlayerPlayFilled)
                        ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStart, $server))
                        ->disabled(fn (Server $server) => $server->isInConflictState() || !$this->status->isStartable()),
                    fn (Server $server) => App::make(ServerStartGate::class)->wouldBlock($server, user()),
                )
                    ->action(function (Server $server) {
                        $decision = App::make(ServerStartGate::class)->gateStart(
                            $server,
                            user(),
                            fn () => $this->dispatch('setServerState', uuid: $server->uuid, state: 'start'),
                        );

                        if (!$decision->proceeded) {
                            $isTransient = $decision->outcome === StartGateDecision::LOCK_TIMEOUT;
                            $notification = Notification::make()
                                ->title($isTransient ? 'Try again in a moment' : 'Could not start server')
                                ->body($decision->message);

                            $isTransient ? $notification->warning() : $notification->danger();
                            $notification->send();

                            return;
                        }

                        // surface the swap explicitly, the live state widget
                        // covers the new servers transition but the user has
                        // no other signal that another of their servers was
                        // stopped to make room.
                        if ($decision->outcome === StartGateDecision::SWAPPED && $decision->stopped !== null) {
                            Notification::make()
                                ->title('Switched servers')
                                ->body("Stopped \"{$decision->stopped->name}\" to start this one.")
                                ->success()
                                ->send();
                        }
                    })
                    ->size(Size::ExtraLarge),
                Action::make('restart')
                    ->label(trans('server/console.power_actions.restart'))
                    ->color('gray')
                    ->icon(TablerIcon::Reload)
                    ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlRestart, $server))
                    ->disabled(fn (Server $server) => $server->isInConflictState() || !$this->status->isRestartable())
                    ->action(fn (Server $server) => $this->dispatch('setServerState', uuid: $server->uuid, state: 'restart'))
                    ->size(Size::ExtraLarge),
                Action::make('stop')
                    ->label(trans('server/console.power_actions.stop'))
                    ->color('danger')
                    ->icon(TablerIcon::PlayerStopFilled)
                    ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStop, $server))
                    ->visible(fn () => !$this->status->isKillable())
                    ->disabled(fn (Server $server) => $server->isInConflictState() || !$this->status->isStoppable())
                    ->action(fn (Server $server) => $this->dispatch('setServerState', uuid: $server->uuid, state: 'stop'))
                    ->size(Size::ExtraLarge),
                Action::make('kill')
                    ->label(trans('server/console.power_actions.kill'))
                    ->color('danger')
                    ->icon(TablerIcon::AlertSquare)
                    ->tooltip(trans('server/console.power_actions.kill_tooltip'))
                    ->requiresConfirmation()
                    ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStop, $server))
                    ->visible(fn () => $this->status->isKillable())
                    ->disabled(fn (Server $server) => $server->isInConflictState() || !$this->status->isKillable())
                    ->action(fn (Server $server) => $this->dispatch('setServerState', uuid: $server->uuid, state: 'kill'))
                    ->size(Size::ExtraLarge),
            ])
                ->record(function () {
                    /** @var Server $server */
                    $server = Filament::getTenant();

                    return $server;
                })
                ->buttonGroup(),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/overview.nav.label');
    }

    public function getTitle(): string
    {
        return trans('server/overview.title');
    }
}
