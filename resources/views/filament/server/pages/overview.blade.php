<x-filament-panels::page class="fi-overview-page">
    {{-- @assets loads once and is preserved across wire:navigate, so the overview
         styles survive SPA navigation onto this page. --}}
    @assets
        @vite(['resources/css/components/overview/overview.css'])
    @endassets
    @php($server = \Filament\Facades\Filament::getTenant())
    @php($containerStatus = $this->status)
    @php($handler = $this->resolveStateHandler($server))
    @php($transferring = $server->transfer !== null && $server->transfer->successful === null)

    @if ($handler !== null)
        {{-- phase 7: plugin state handler owns the full body --}}
        {!! $handler->render($server) !!}
    @elseif ($server->status === \App\Enums\ServerState::Suspended)
        {{-- phase 6: suspended renders without a page head --}}
        @include('filament.server.pages.overview-states.suspended', compact('server'))
    @elseif ($transferring)
        <x-overview.page-head
            :server="$server"
            :container-status="$containerStatus"
            :transferring="$transferring"
            class="overview-body-spacer"
        />
        @include('filament.server.pages.overview-states.transferring', compact('server'))
    @elseif ($server->node_id !== null)
        <x-overview.page-head
            :server="$server"
            :container-status="$containerStatus"
            :transferring="$transferring"
            class="overview-body-spacer"
        />

        {{-- @switch(true) lets each case combine ServerState + ContainerStatus.
             do not refactor to @switch($server->status), the predicates compose both. --}}
        @switch(true)
            @case ($server->status === \App\Enums\ServerState::Installing)
            @case ($server->status === \App\Enums\ServerState::InstallFailed)
            @case ($server->status === \App\Enums\ServerState::ReinstallFailed)
                @include('filament.server.pages.overview-states.installing', compact('server'))
                @break
            @case ($server->status === \App\Enums\ServerState::RestoringBackup)
                @include('filament.server.pages.overview-states.transient', compact('server', 'containerStatus'))
                @break
            @case ($server->status === null && $containerStatus === \App\Enums\ContainerStatus::Running)
                @include('filament.server.pages.overview-states.running', compact('server'))
                @break
            @case ($server->status === null && in_array($containerStatus, [\App\Enums\ContainerStatus::Starting, \App\Enums\ContainerStatus::Stopping, \App\Enums\ContainerStatus::Restarting], true))
                @include('filament.server.pages.overview-states.transient', compact('server', 'containerStatus'))
                @break
            @default
                @include('filament.server.pages.overview-states.stopped', compact('server'))
        @endswitch
    @else
        {{-- no-node fallback. reachable when a stash-family server has no
             registered state handler, i.e. the stash-manager plugin is
             missing or disabled (when installed, its StashStateHandler owns
             the body via the @if above). cannot render the stopped partial
             here, it hardcodes ServerConsole which dereferences
             $server->node and crashes on null, so this stays a minimal
             inline placeholder. --}}
        <div class="overview-banner overview-banner--default">
            <div class="overview-banner__accent"></div>
            <div class="overview-banner__body">
                <x-filament::icon icon="tabler-archive" class="size-5 overview-banner__icon" />
                <div class="overview-banner__content">
                    <p class="overview-banner__title">
                        @switch($server->status)
                            @case (\App\Enums\ServerState::Stashed)
                                This server is in cold storage.
                                @break
                            @case (\App\Enums\ServerState::Retrieving)
                                Retrieving from cold storage.
                                @break
                            @case (\App\Enums\ServerState::Stashing)
                                Moving to cold storage.
                                @break
                            @default
                                This server is not currently assigned to a node.
                        @endswitch
                    </p>
                    <p class="overview-banner__subtitle">
                        @switch($server->status)
                            @case (\App\Enums\ServerState::Stashed)
                                This server is in long-term storage. Reload to load the full view, or contact support if it keeps landing here.
                                @break
                            @case (\App\Enums\ServerState::Retrieving)
                            @case (\App\Enums\ServerState::Stashing)
                                This usually takes about a minute. Reload shortly.
                                @break
                            @default
                                Reload in a moment, the panel is still wiring this server up. If this persists, contact support.
                        @endswitch
                    </p>
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
