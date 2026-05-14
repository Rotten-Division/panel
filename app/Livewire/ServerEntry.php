<?php

namespace App\Livewire;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Filament\Server\Pages\Overview;
use App\Models\Server;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Facades\FilamentView;
use Illuminate\View\View;
use Livewire\Component;

// the grid view of the dashboard renders ListServers::getPowerActionGroup
// inside this component, so it has to be a Filament HasActions and HasSchemas
// host or any click on a power action posts mountAction to a component that
// does not implement it, the panel returns a 500 from livewire update.
class ServerEntry extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public Server $server;

    private ?ActionGroup $powerActionsGroup = null;

    public function boot(): void
    {
        // cache every action in the power group on this component so
        // filaments resolveAction can find start restart stop and kill by
        // name on mountAction. without this the inline blade render only
        // built the group for display and the actions were never associated
        // with the calling component, mountAction returned null and the
        // action was discarded on click without invoking the closure.
        // boot fires on every request before any method call, so the cache
        // is ready before mountAction runs.
        foreach ($this->resolvePowerActions()->getFlatActions() as $action) {
            $this->cacheAction($action);
        }
    }

    public function powerActions(): ActionGroup
    {
        return $this->resolvePowerActions();
    }

    private function resolvePowerActions(): ActionGroup
    {
        if ($this->powerActionsGroup === null) {
            $this->powerActionsGroup = ListServers::getPowerActionGroup()->record($this->server);
        }

        return $this->powerActionsGroup;
    }

    public function render(): View
    {
        return view('livewire.server-entry', ['component' => $this]);
    }

    public function placeholder(): View
    {
        return view('livewire.server-entry-placeholder', ['server' => $this->server, 'component' => $this]);
    }

    public function redirectUrl(?bool $shouldOpenUrlInNewTab = false): string
    {
        $url = Overview::getUrl(panel: 'server', tenant: $this->server);
        $target = $shouldOpenUrlInNewTab ? '_blank' : '_self';

        if (!$shouldOpenUrlInNewTab && FilamentView::hasSpaMode($url)) {
            return sprintf("Livewire.navigate('%s')", $url);
        }

        return sprintf("window.open('%s', '%s')", $url, $target);
    }
}
