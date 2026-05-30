@php
    // freeze the charts when the container is on its way down. wings has
    // already cut the stats stream so the same cached values would just
    // re-render on every poll, making the chart look "live" when it isn't.
    $frozen = ($containerStatus ?? null) === \App\Enums\ContainerStatus::Stopping;
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    @livewire(\App\Filament\Server\Widgets\ServerCpuChart::class, ['server' => $server, 'frozen' => $frozen], key("transient-cpu-{$server->id}"))
    @livewire(\App\Filament\Server\Widgets\ServerMemoryChart::class, ['server' => $server, 'frozen' => $frozen], key("transient-memory-{$server->id}"))
    @livewire(\App\Filament\Server\Widgets\ServerNetworkChart::class, ['server' => $server, 'frozen' => $frozen], key("transient-network-{$server->id}"))
</div>
