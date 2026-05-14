<x-filament-panels::page>
    <div class="space-y-10">
        <section>
            <h2 class="text-lg font-semibold mb-3">StatePill</h2>
            <div class="flex flex-wrap gap-3">
                @foreach ($pillVariants as $p)
                    <div class="flex flex-col gap-1">
                        <x-overview.state-pill
                            :state="$p['state']"
                            :containerStatus="$p['container']"
                            :transferring="$p['transferring']"
                        />
                        <span class="text-xs text-gray-500">{{ $p['caption'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">CountryFlag</h2>
            <div class="flex items-center gap-4">
                <span><x-overview.country-flag code="gb" /> <span class="text-xs text-gray-500 ml-1">gb</span></span>
                <span><x-overview.country-flag code="GB" /> <span class="text-xs text-gray-500 ml-1">GB (uppercase)</span></span>
                <span><x-overview.country-flag code="uk" /> <span class="text-xs text-gray-500 ml-1">uk (alias)</span></span>
                <span><x-overview.country-flag code="de" /> <span class="text-xs text-gray-500 ml-1">de (placeholder)</span></span>
                <span><x-overview.country-flag code="us" /> <span class="text-xs text-gray-500 ml-1">us (placeholder)</span></span>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">StateBanner</h2>
            <div class="space-y-3">
                @foreach ($bannerVariants as $b)
                    <x-overview.state-banner
                        :variant="$b['variant']"
                        :title="$b['title']"
                        :subtitle="$b['subtitle']"
                        :show-progress="$b['progress']"
                        :icon="$b['icon']"
                    />
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">StageHero</h2>
            <div class="space-y-4">
                @foreach ($heroVariants as $h)
                    <x-overview.stage-hero :variant="$h['variant']">
                        <x-slot:title>{{ $h['title'] }}</x-slot:title>
                        <x-slot:body>{{ $h['body'] }}</x-slot:body>
                        @if ($h['variant'] === 'rouse')
                            <x-slot:cta>
                                <button class="overview-power-button overview-power-button--start" type="button">
                                    <x-filament::icon icon="tabler-bolt" class="size-3.5" />
                                    Wake server
                                </button>
                            </x-slot:cta>
                        @endif
                    </x-overview.stage-hero>
                @endforeach
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">FactCards</h2>
            <div class="space-y-3">
                <x-overview.fact-cards :cards="$factSamples['three']" />
                <x-overview.fact-cards :cards="$factSamples['two']" />
                <x-overview.fact-cards :cards="$factSamples['warn']" />
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">PowerButtons</h2>
            <p class="text-sm text-gray-500 mb-3">Live rendering requires a Server instance and the Overview page's wire host, see the per-server overview page for the wired version.</p>
            <div class="overview-power-buttons flex items-center gap-2">
                <button type="button" class="overview-power-button overview-power-button--start">
                    <x-filament::icon icon="tabler-player-play-filled" class="size-3.5" /><span>Start</span>
                </button>
                <button type="button" class="overview-power-button" disabled>
                    <x-filament::icon icon="tabler-rotate-clockwise-2" class="size-3.5" /><span>Restart</span>
                </button>
                <button type="button" class="overview-power-button overview-power-button--stop" disabled>
                    <x-filament::icon icon="tabler-player-stop-filled" class="size-3.5" /><span>Stop</span>
                </button>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">PageHead (stub address)</h2>
            <p class="text-sm text-gray-500 mb-3">PageHead reads from a live Server. Below is the rendered shape using the same CSS hooks.</p>
            <div class="overview-page-head flex items-start justify-between gap-4">
                <div class="flex flex-col gap-2 flex-1 min-w-0">
                    <h1 class="overview-page-head__address font-mono font-medium text-2xl text-[var(--linen)]">
                        <span>{{ Str::before($sampleAddress, ':') }}</span><span class="text-[var(--hearth)]">:{{ Str::after($sampleAddress, ':') }}</span>
                    </h1>
                    <div class="overview-page-head__loc flex items-center gap-2">
                        <x-overview.country-flag code="gb" />
                        <span class="font-mono text-xs text-[var(--sand)]">London, GB</span>
                    </div>
                </div>
                <button type="button" class="overview-page-head__copy inline-flex items-center justify-center size-8 rounded border border-[var(--graphite)] bg-[var(--ink)] hover:border-[var(--hearth)] transition-colors">
                    <x-filament::icon icon="tabler-copy" class="size-4" />
                </button>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold mb-3">ResourceCard (synthetic)</h2>
            <p class="text-sm text-gray-500 mb-3">Rendered against fabricated samples without a live server. Live cards on a server's Overview page poll every 5s.</p>
            @php
                use App\Support\ResourceCard;
                $cpuSeries = [12, 18, 25, 28, 22, 30, 35, 40, 38, 42, 50, 55, 60, 58, 65, 70, 68, 72, 75, 80, 78, 82, 85, 90];
                $cpuTicks = ResourceCard::ticks(array_map('floatval', $cpuSeries));
                $cpuCard = [
                    'label' => 'CPU',
                    'unit' => '%',
                    'current' => '90.0%',
                    'allocation' => '200%',
                    'progress' => ['value' => 90.0, 'max' => 200.0, 'colour' => 'honey'],
                    'ticks' => array_map(fn ($v) => number_format($v, 0) . '%', $cpuTicks),
                    'series' => ResourceCard::points(array_map('floatval', $cpuSeries), $cpuTicks[0], $cpuTicks[2]),
                ];
            @endphp
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @include('filament.server.widgets.resource-card', ['card' => $cpuCard])
            </div>
        </section>
    </div>
</x-filament-panels::page>
