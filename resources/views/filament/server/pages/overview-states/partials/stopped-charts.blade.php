{{-- render the full Filament resource-card widgets (axes, gridlines,
     legend) using the same cached series the running state reads. each
     card sits inside the offline overlay so the blur + chip layer on
     top while the underlying chart still resolves at full fidelity. --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <x-overview.offline-card>
        @livewire(\App\Filament\Server\Widgets\ServerCpuChart::class, ['server' => $server, 'frozen' => true], key("stopped-cpu-{$server->id}"))
    </x-overview.offline-card>
    <x-overview.offline-card>
        @livewire(\App\Filament\Server\Widgets\ServerMemoryChart::class, ['server' => $server, 'frozen' => true], key("stopped-memory-{$server->id}"))
    </x-overview.offline-card>
    <x-overview.offline-card>
        @livewire(\App\Filament\Server\Widgets\ServerNetworkChart::class, ['server' => $server, 'frozen' => true], key("stopped-network-{$server->id}"))
    </x-overview.offline-card>
</div>
