<?php

namespace App\Filament\Server\Pages;

use App\Contracts\Servers\PlayerCountProvider;
use App\Enums\ConsoleWidgetPosition;
use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Enums\TablerIcon;
use App\Exceptions\Http\Server\ServerStateConflictException;
use App\Extensions\Features\FeatureService;
use App\Filament\Server\Widgets\ServerConsole;
use App\Filament\Server\Widgets\ServerCpuChart;
use App\Filament\Server\Widgets\ServerMemoryChart;
use App\Filament\Server\Widgets\ServerNetworkChart;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Traits\Filament\CanCustomizeHeaderActions;
use BackedEnum;
use Carbon\CarbonInterval;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\Concerns\HasHeaderActions;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\View\View;
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

    // page-head IS the heading — suppress Filament's default <h1>Overview</h1>
    // that would otherwise render at the top of the page body now that
    // getHeader() is gone.
    protected ?string $heading = null;

    // suppress Filament's default header chrome entirely. without this
    // override the parent Page renders a header strip containing the page
    // title and getHeaderActions() output, which duplicates the power
    // buttons that the page-head right rail now owns.
    public function getHeader(): ?View
    {
        return null;
    }

    public ContainerStatus $status = ContainerStatus::Offline;

    public ?int $playerCount = null;

    public ?int $playerLimit = null;

    public int $diskUsedBytes = 0;

    public int $uptimeMs = 0;

    protected FeatureService $featureService;

    public function mount(): void
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        // hydrate the container status from the wings stats cache so the
        // initial render dispatches to the right state partial. without
        // this the page flashes Stopped for a beat before the websocket
        // first console-status event flips $this->status.
        $cached = cache()->get("servers.$server->uuid.status");
        if ($cached instanceof ContainerStatus) {
            $this->status = $cached;
        }

        $this->refreshLiveData();

        try {
            $server->validateCurrentState();
        } catch (ServerStateConflictException $exception) {
            // every conflict state now renders its own inline state-banner
            // through the overview-states partials (installing, transferring,
            // suspended, nest, restoring-backup), all carrying brand-voice
            // copy and the right action. the legacy session-scoped
            // AlertBanner duplicates that and stacks above the new layout.
            // suppress it for any state we know has a dedicated partial.
            $stateHasOwnBanner = in_array($server->status, [
                ServerState::Installing,
                ServerState::InstallFailed,
                ServerState::ReinstallFailed,
                ServerState::RestoringBackup,
                ServerState::Suspended,
                ServerState::Nest,
                ServerState::Hydrating,
                ServerState::Capturing,
            ], true) || ($server->transfer !== null && $server->transfer->successful === null);
            if ($stateHasOwnBanner) {
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
        // (ServerConsole, *Chart) crash on $server->node->X.
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

        // wings stats are cached per field at `servers.{id}.{field}` by the
        // ServerConsole widget's store-stats handler. each value is a time
        // series keyed by timestamp, capped at 120 samples; we read the
        // most recent one for the live stat grid.
        $this->diskUsedBytes = $this->latestStatsValue($server, 'disk_bytes');
        $this->uptimeMs = $this->latestStatsValue($server, 'uptime');

        // wings keeps the pre-stop uptime in cache for a tick after stop,
        // forcing zero here so the grid doesn't show stale 'running for 3h'
        // copy on a server the user just stopped.
        if (in_array($this->status, [ContainerStatus::Offline, ContainerStatus::Stopping], true)) {
            $this->uptimeMs = 0;
        }

        // player count contract returns null by default; live stats plugin
        // would rebind to return real values.
        $payload = App::make(PlayerCountProvider::class)->resolve($server);
        $this->playerCount = $payload['current'] ?? null;
        $this->playerLimit = $payload['max'] ?? null;

        // single cadence source for the page. the CPU/Memory/Network chart
        // widgets listen for `refresh-overview` on their `refreshSeries`
        // handler so all four cards refresh against the same tick.
        $this->dispatch('refresh-overview');
    }

    private function latestStatsValue(Server $server, string $field): int
    {
        $series = cache()->get("servers.$server->id.$field");
        if (!is_array($series) || $series === []) {
            return 0;
        }

        return (int) end($series);
    }

    public function uptimeLabel(): ?string
    {
        if ($this->uptimeMs <= 0) {
            return null;
        }

        return CarbonInterval::milliseconds($this->uptimeMs)
            ->cascade()
            ->forHumans(['short' => true, 'parts' => 2]);
    }

    /**
     * tone for the disk utilisation bar across every state partial. 60%
     * switches to warning, 85% to danger.
     */
    public function diskBarTone(): string
    {
        $pct = $this->diskUsedPercent();

        return match (true) {
            $pct >= 85 => 'danger',
            $pct >= 60 => 'warning',
            default => 'success',
        };
    }

    /**
     * disk used percentage clamped to 0..100. partials use this for the
     * bar width and the tone helper above.
     */
    public function diskUsedPercent(): float
    {
        /** @var Server $server */
        $server = Filament::getTenant();
        $limit = (int) ($server?->disk ?? 0) * 1024 * 1024;
        if ($limit <= 0) {
            return 0.0;
        }

        return min(100.0, ($this->diskUsedBytes / $limit) * 100);
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
