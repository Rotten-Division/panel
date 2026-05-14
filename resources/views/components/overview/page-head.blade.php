@props(['address', 'host', 'port', 'city', 'cc'])

<div {{ $attributes->merge(['class' => 'overview-page-head flex items-start justify-between gap-4']) }}>
    <div class="flex flex-col gap-2 flex-1 min-w-0">
        <h1 class="overview-page-head__address font-mono font-medium text-2xl">
            <span>{{ $host }}</span>@if ($port)<span class="overview-page-head__address-port">:{{ $port }}</span>@endif
        </h1>

        @if ($city && $cc)
            <div class="overview-page-head__loc flex items-center gap-2">
                <x-overview.country-flag :code="$cc" />
                <span class="overview-page-head__loc-city font-mono text-xs">
                    {{ ucfirst($city) }}, {{ strtoupper($cc) }}
                </span>
            </div>
        @endif
    </div>

    @if ($address !== '')
        <button
            type="button"
            class="overview-page-head__copy inline-flex items-center justify-center size-8 rounded"
            x-data
            x-on:click="navigator.clipboard.writeText(@js($address))"
            aria-label="Copy server address"
        >
            <x-filament::icon icon="tabler-copy" class="size-4" />
        </button>
    @endif
</div>
