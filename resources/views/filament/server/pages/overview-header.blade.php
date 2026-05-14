@props([
    'server',
    'eyebrow',
    'state',
    'containerStatus',
    'transferActive',
    'headerActions' => [],
])

{{-- Server panel overview-page topbar. left rail carries the game ·
     flavour · version eyebrow that used to live in the page body.
     right rail carries state pill + power buttons. Phase 4 supplies
     the StatePill + PowerButtons components, until then placeholders
     render the raw state value so the row is testable. --}}
<div class="fi-overview-topbar flex items-center justify-between gap-4 px-4 py-3 border-b border-gray-200 dark:border-gray-800">
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
        {{-- Phase 4 supplies <x-overview.state-pill /> + <x-overview.power-buttons />.
             Until then the existing Filament header actions (start, restart,
             stop, kill) render here so the page stays functional. --}}
        <span class="text-xs text-gray-400" data-placeholder="state-pill">{{ $state?->value }}</span>
        @if (filled($headerActions))
            <x-filament::actions :actions="$headerActions" />
        @endif
    </div>
</div>
