<?php

namespace App\Providers;

use App\Checks\CacheCheck;
use App\Checks\DatabaseCheck;
use App\Checks\DebugModeCheck;
use App\Checks\EnvironmentCheck;
use App\Checks\NodeVersionsCheck;
use App\Checks\PanelVersionCheck;
use App\Checks\ScheduleCheck;
use App\Checks\UsedDiskSpaceCheck;
use App\Contracts\Auth\SelfServiceRegistrationPolicy;
use App\Contracts\Servers\PortHoldGate;
use App\Contracts\Servers\ServerStartGate;
use App\Http\Responses\LoginResponse;
use App\Models\Allocation;
use App\Models\ApiKey;
use App\Models\Backup;
use App\Models\Database;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Node;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use App\Models\UserSSHKey;
use App\Services\Auth\AlwaysAllowRegistrationPolicy;
use App\Services\Helpers\PluginService;
use App\Services\Helpers\SoftwareVersionService;
use App\Services\Servers\NoPortHoldsGate;
use App\Services\Servers\UnrestrictedServerStartGate;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Health\Facades\Health;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(
        Application $app,
        SoftwareVersionService $versionService,
        Repository $config,
    ): void {
        // If the APP_URL value is set with https:// make sure we force it here. Theoretically
        // this should just work with the proxy logic, but there are a lot of cases where it
        // doesn't, and it triggers a lot of support requests, so lets just head it off here.
        URL::forceHttps(Str::startsWith(config('app.url') ?? '', 'https://'));

        if ($app->runningInConsole() && empty(config('app.key'))) {
            $config->set('app.key', '');
        }

        Relation::enforceMorphMap([
            'allocation' => Allocation::class,
            'api_key' => ApiKey::class,
            'backup' => Backup::class,
            'database' => Database::class,
            'egg' => Egg::class,
            'egg_variable' => EggVariable::class,
            'schedule' => Schedule::class,
            'server' => Server::class,
            'ssh_key' => UserSSHKey::class,
            'task' => Task::class,
            'user' => User::class,
            'node' => Node::class,
        ]);

        Http::macro(
            'daemon',
            fn (Node $node, array $headers = []) => Http::acceptJson()
                ->asJson()
                ->withToken($node->daemon_token)
                ->withHeaders($headers)
                ->withOptions(['verify' => (bool) $app->environment('production')])
                ->timeout(config('panel.guzzle.timeout'))
                ->connectTimeout(config('panel.guzzle.connect_timeout'))
                ->baseUrl($node->getConnectionAddress())
        );

        Sanctum::usePersonalAccessTokenModel(ApiKey::class);

        Gate::define('viewApiDocs', fn () => true);

        $bearerTokens = fn (OpenApi $openApi) => $openApi->secure(SecurityScheme::http('bearer'));
        Scramble::registerApi('application', ['api_path' => 'api/application', 'info' => ['version' => '1.0']])->afterOpenApiGenerated($bearerTokens);
        Scramble::registerApi('client', ['api_path' => 'api/client', 'info' => ['version' => '1.0']])->afterOpenApiGenerated($bearerTokens);

        // Don't run any health checks during tests
        if (!$app->runningUnitTests()) {
            Health::checks([
                DebugModeCheck::new()->if($app->isProduction()),
                EnvironmentCheck::new(),
                CacheCheck::new(),
                DatabaseCheck::new(),
                ScheduleCheck::new(),
                UsedDiskSpaceCheck::new(),
                PanelVersionCheck::new(),
                NodeVersionsCheck::new(),
            ]);
        }

        Gate::before(fn (User $user, $ability) => $user->isRootAdmin() ? true : null);

        $latestPanel = $versionService->latestPanelVersion();
        AboutCommand::add('Pelican', [
            'Panel Version' => $versionService->currentPanelVersion(),
            // hide the literal error sentinel that latestPanelVersion emits
            // when the releases endpoint 404s or fails, an unknown answer
            // reads better as a dash than the word error.
            'Latest Version' => $latestPanel === 'error' ? '-' : $latestPanel,
            'Up-to-Date' => $versionService->isLatestPanel() ? '<fg=green;options=bold>Yes</>' : '<fg=red;options=bold>No</>',
        ]);

        AboutCommand::add('Drivers', 'Backups', config('backups.default'));

        AboutCommand::add('Environment', 'Installation Directory', base_path());
    }

    /**
     * Register application service providers.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);

        // default start gate is unrestricted, the user limits plugin rebinds
        // this to a swap aware implementation when installed. singleton
        // because the gate is stateless and every start path resolves it.
        $this->app->singleton(ServerStartGate::class, UnrestrictedServerStartGate::class);

        // default registration policy allows every self service path. the
        // onboarding plugin rebinds this to a settings backed implementation
        // that can pause new account creation across the register page and
        // the OAuth first time sign in flow.
        $this->app->singleton(SelfServiceRegistrationPolicy::class, AlwaysAllowRegistrationPolicy::class);

        // default port hold gate is empty, the nest manager plugin rebinds
        // this to a reader against osnm_port_holds so the wizard's allocation
        // resolver skips ports reserved for nest restores.
        $this->app->singleton(PortHoldGate::class, NoPortHoldsGate::class);

        Scramble::ignoreDefaultRoutes();

        /** @var PluginService $pluginService */
        $pluginService = app(PluginService::class); // @phpstan-ignore myCustomRules.forbiddenGlobalFunctions

        $pluginService->loadPlugins();
    }
}
