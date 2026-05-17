@props([
    'server',
    'state',
    'containerStatus',
    'transferActive',
])

{{-- Server panel overview-page topbar. eyebrow (game · flavour · version)
     moved into the page-head component above the connection address; the
     topbar now carries only the state pill + power buttons on the right. --}}
<div class="fi-overview-topbar overview-topbar flex items-center justify-end gap-3 px-4 py-3">
    <x-overview.state-pill
        :state="$state"
        :containerStatus="$containerStatus"
        :transferring="$transferActive"
    />
    <x-overview.power-buttons :server="$server" :containerStatus="$containerStatus" />
</div>
