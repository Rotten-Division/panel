<?php

namespace App\Filament\Server\Pages;

use App\Enums\ConsoleWidgetPosition;
use App\Enums\ContainerStatus;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Extensions\Features\FeatureService;
use App\Filament\Server\Widgets\ServerConsole;
use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use App\Filament\Server\Widgets\ServerOverview;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Traits\Filament\CanCustomizeHeaderActions;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Filament\Support\Enums\Size;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Livewire\Attributes\On;

class Console extends Page
{
    private const LIMIT_SERVICE = 'RottenDivision\\OspiteUserLimits\\Services\\LimitService';

    use CanCustomizeHeaderActions, HasHeaderActions {
        CanCustomizeHeaderActions::getHeaderActions insteadof HasHeaderActions;
    }
    use InteractsWithActions;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Terminal2;

    protected string $view = 'filament.server.pages.console';

    public ContainerStatus $status = ContainerStatus::Offline;

    protected FeatureService $featureService;

    public function mount(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException $exception) {
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

        $this->headerActions($this->getHeaderActions());
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('start')
                    ->label(trans('server/console.power_actions.start'))
                    ->color('primary')
                    ->icon(TablerIcon::PlayerPlayFilled)
                    ->authorize(fn (Server $server) => user()?->can(SubuserPermission::ControlStart, $server))
                    ->disabled(fn (Server $server) => $server->isInConflictState() || !$this->status->isStartable())
                    ->requiresConfirmation(fn (Server $server) => $this->blockingServerFor($server) !== null)
                    ->modalHeading(trans('server/console.power_actions.start_swap_heading'))
                    ->modalIcon(TablerIcon::AlertTriangle)
                    ->modalIconColor('danger')
                    ->modalDescription(function (Server $server) {
                        $other = $this->blockingServerFor($server);

                        return $other
                            ? trans('server/console.power_actions.start_swap_description', ['name' => $other->name])
                            : null;
                    })
                    ->modalSubmitActionLabel(trans('server/console.power_actions.start_swap_submit'))
                    ->modalSubmitAction(fn ($action) => $action->color('danger'))
                    ->action(function (Server $server) {
                        // serialise concurrent start clicks per owner so two
                        // tabs on different servers cannot both pass the
                        // running gate inside the wings status cache window.
                        // the lock is held only across the gate re check and
                        // dispatch, wings does the rest of the work async.
                        $lock = Cache::lock("ospite:server-start:{$server->owner_id}", 30);

                        try {
                            $lock->block(5);
                        } catch (LockTimeoutException) {
                            Notification::make()
                                ->title('Try again in a moment')
                                ->body('Another start is already in flight for your account.')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            $other = $this->blockingServerFor($server);

                            if ($other !== null) {
                                // re check ControlStop on the other server
                                // before sending the kill, the gate only fires
                                // for owners but a panel admin power tweak
                                // could lift that in the future, the auth
                                // check is the safety net.
                                if (!user()?->can(SubuserPermission::ControlStop, $other)) {
                                    Notification::make()
                                        ->title('Permission denied')
                                        ->body("You do not have permission to stop \"{$other->name}\".")
                                        ->danger()
                                        ->send();

                                    return;
                                }

                                // browser side setServerState filters by the
                                // current server uuid, so the other server
                                // stop has to go straight through the panels
                                // wings client.
                                app(DaemonServerRepository::class)->setServer($other)->power('stop');
                            }

                            // pre seed the live status cache so a second tab
                            // hitting the gate before wings reports back sees
                            // this server as starting and triggers the swap
                            // path. wings overwrites it via the normal 15s
                            // refresh on the next status fetch.
                            Cache::put(
                                "servers.{$server->uuid}.status",
                                ContainerStatus::Starting,
                                now()->addSeconds(20),
                            );

                            $this->dispatch('setServerState', uuid: $server->uuid, state: 'start');
                        } finally {
                            $lock->release();
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
        return trans('server/console.title');
    }

    public function getTitle(): string
    {
        return trans('server/console.title');
    }

    /**
     * resolve the running server gate when the ospite user limits plugin is
     * installed. without it the gate degrades to off and the start action
     * behaves like upstream pelican, so panels that drop the plugin keep
     * working. the gate only fires when the acting user is the owner, so a
     * subuser starting their hosts server cannot trigger a stop on another
     * server they have no permission over.
     */
    private function blockingServerFor(Server $server): ?Server
    {
        $service = self::LIMIT_SERVICE;

        if (!class_exists($service)) {
            return null;
        }

        $acting = user();

        if ($acting === null || $acting->id !== $server->owner_id) {
            return null;
        }

        return app($service)->blockingActiveServerFor($acting, $server);
    }
}
