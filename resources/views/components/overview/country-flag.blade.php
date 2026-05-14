@props(['code'])

@php($code = strtolower((string) $code))

<span class="overview-country-flag inline-flex items-center justify-center">
    @switch($code)
        @case('gb')
        @case('uk')
            <x-overview.country-flag.gb />
            @break
        @default
            <span class="overview-country-flag__placeholder">{{ strtoupper($code) }}</span>
    @endswitch
</span>
