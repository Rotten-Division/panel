@props(['address', 'host', 'port', 'city', 'cc'])

<div {{ $attributes->merge(['class' => 'overview-page-head flex items-end justify-between gap-4 pb-3 border-b border-[var(--graphite)]']) }}>
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-3 min-w-0">
            <h1 class="overview-page-head__address font-mono font-medium text-2xl text-[var(--linen)] truncate">
                <span>{{ $host }}</span>@if ($port)<span class="text-[var(--hearth)]">:{{ $port }}</span>@endif
            </h1>

            @if ($city && $cc)
                <span class="inline-flex items-center gap-[7px] flex-none font-mono text-xs text-[var(--sand)] leading-none">
                    <x-overview.country-flag :code="$cc" />
                    <span>{{ ucfirst($city) }}, {{ strtoupper($cc) }}</span>
                </span>
            @endif
        </div>
    </div>

    @if ($address !== '')
        <button
            type="button"
            class="inline-flex items-center justify-center size-[34px] rounded-md bg-[var(--ink)] border border-[var(--graphite)] text-[var(--sand)] hover:text-[var(--linen)] hover:border-[var(--hearth)] transition-colors flex-none"
            x-data
            x-on:click="navigator.clipboard.writeText(@js($address))"
            aria-label="Copy server address"
        >
            <x-filament::icon icon="tabler-copy" class="size-4" />
        </button>
    @endif
</div>
