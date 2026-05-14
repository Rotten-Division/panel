@props([
    'server',
    'eyebrow',
    'state',
    'containerStatus',
    'transferActive',
])

{{-- Server panel overview-page topbar. left rail carries the game ·
     flavour · version eyebrow that used to live in the page body.
     right rail carries state pill + power buttons. --}}
<div class="fi-overview-topbar overview-topbar flex items-center justify-between gap-4 px-4 py-3">
    <div class="fi-overview-eyebrow flex items-center gap-2 text-xs font-medium tracking-wider uppercase text-gray-500 dark:text-gray-400">
        @if ($eyebrow['game'])
            <span>{{ $eyebrow['game'] }}</span>
        @endif
        @if ($eyebrow['game'] && $eyebrow['flavour'])
            <span aria-hidden="true">·</span>
        @endif
        @if ($eyebrow['flavour'])
            <span>{{ $eyebrow['flavour'] }}</span>
        @endif
        @if ($eyebrow['flavour'] && $eyebrow['version'])
            <span aria-hidden="true">·</span>
        @endif
        @if ($eyebrow['version'])
            <span>{{ $eyebrow['version'] }}</span>
        @endif
    </div>

    <div class="fi-overview-topbar-actions flex items-center gap-3">
        <x-overview.state-pill
            :state="$state"
            :containerStatus="$containerStatus"
            :transferring="$transferActive"
        />
        <x-overview.power-buttons :server="$server" :containerStatus="$containerStatus" />
    </div>
</div>
