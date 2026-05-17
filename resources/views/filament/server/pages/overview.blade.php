<x-filament-panels::page class="fi-overview-page">
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
        {{-- no-node fallback. only reachable if a state handler is missing for
             a nest-family or suspended server, both of which match above. --}}
        @include('filament.server.pages.overview-states.stopped', compact('server'))
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
