@props([
    'server',
    'host',
    'port',
    'city',
    'cc',
    'flavour' => null,
    'version' => null,
    'state' => null,
    'containerStatus' => null,
    'transferring' => false,
])

@php
    $eyebrowParts = array_values(array_filter([$flavour, $version]));
@endphp

<div {{ $attributes->merge(['class' => 'overview-page-head flex items-end justify-between gap-4 pb-5 border-b border-[var(--graphite)]']) }}>
    <div class="flex-1 min-w-0">
        @if (! empty($eyebrowParts))
            <div class="flex items-center gap-2 mb-3 text-xs font-medium tracking-[0.16em] uppercase text-[var(--stone)]">
                @foreach ($eyebrowParts as $i => $part)
                    @if ($i > 0)
                        <span aria-hidden="true">·</span>
                    @endif
                    <span>{{ $part }}</span>
                @endforeach
            </div>
        @endif

        <div class="flex items-center gap-4 min-w-0">
            <h1 class="overview-page-head__address font-mono font-medium text-4xl text-[var(--linen)] truncate leading-none">
                <span>{{ $host }}</span>@if ($port)<span class="text-[var(--hearth)]">:{{ $port }}</span>@endif
            </h1>

            @if ($city && $cc)
                <span class="inline-flex items-center gap-2 flex-none font-mono text-sm text-[var(--sand)] leading-none">
                    <x-overview.country-flag :code="$cc" />
                    <span>{{ ucfirst($city) }}, {{ strtoupper($cc) }}</span>
                </span>
            @endif
        </div>
    </div>

    <div class="flex items-center gap-3 flex-none">
        <x-overview.state-pill
            :state="$state"
            :containerStatus="$containerStatus"
            :transferring="$transferring"
        />
        <x-overview.power-buttons
            :server="$server"
            :containerStatus="$containerStatus"
        />
    </div>
</div>
