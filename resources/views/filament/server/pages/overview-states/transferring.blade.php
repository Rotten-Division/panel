@php
    /** @var \App\Models\Server $server */
    $sourceName = $server->transfer?->oldNode?->name ?? 'old node';
    $destinationName = $server->transfer?->newNode?->name ?? 'new node';
@endphp

<div wire:poll.1s="refreshLiveData">
    <x-overview.state-banner
        variant="transient"
        title="Moving to a new home"
        :subtitle="$sourceName . ' → ' . $destinationName"
        icon="tabler-arrow-right"
    />

    <x-overview.progress-band />

    {{-- wings posts transfer progress to the panel server-side, so the websocket bridge never fires for byte updates.
         the wrapper poll above triggers a livewire round trip which
         re-renders the page body and re-reads $server->transferProgress
         inside TransferDetail. --}}
    <x-overview.transfer-detail :server="$server" />
</div>
