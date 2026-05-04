<?php

namespace App\Livewire;

use App\Filament\App\Resources\Servers\Pages\ListServers;
use App\Filament\Server\Pages\Console;
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

    /**
     * expose the power action group as a method on the component so
     * Filaments action resolver can find each child action by name when
     * mountAction fires. without this the blade was building the group
     * inline and Filament could not look up start restart stop or kill
     * by name from the calling component, mountAction returned null and
     * the action was discarded on click without invoking the closure.
     */
    public function powerActions(): ActionGroup
    {
        return ListServers::getPowerActionGroup()->record($this->server);
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
        $url = Console::getUrl(panel: 'server', tenant: $this->server);
        $target = $shouldOpenUrlInNewTab ? '_blank' : '_self';

        if (!$shouldOpenUrlInNewTab && FilamentView::hasSpaMode($url)) {
            return sprintf("Livewire.navigate('%s')", $url);
        }

        return sprintf("window.open('%s', '%s')", $url, $target);
    }
}
