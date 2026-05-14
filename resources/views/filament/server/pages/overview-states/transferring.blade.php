@php
    /** @var \App\Models\Server $server */
    $sourceName = $server->transfer?->oldNode?->name ?? 'old node';
    $destinationName = $server->transfer?->newNode?->name ?? 'new node';
@endphp

<x-overview.state-banner
    variant="transient"
    title="Moving to a new home"
    :subtitle="$sourceName . ' → ' . $destinationName"
    icon="tabler-arrow-right"
    :show-progress="true"
/>

<x-overview.transfer-detail :server="$server" />
