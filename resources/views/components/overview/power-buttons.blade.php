@php
    /** @var \App\View\Components\Overview\PowerButtons $component */
@endphp

@if (! $component->shouldHide())
    <div class="overview-power-buttons flex items-center gap-2">
        <button
            type="button"
            wire:click="mountAction('start')"
            @disabled(! $component->canStart())
            class="overview-power-button overview-power-button--start"
        >
            <x-filament::icon icon="tabler-player-play-filled" class="size-3.5" />
            <span>{{ trans('server/console.power_actions.start') }}</span>
        </button>

        <button
            type="button"
            wire:click="mountAction('restart')"
            @disabled(! $component->canRestart())
            class="overview-power-button"
        >
            <x-filament::icon icon="tabler-rotate-clockwise-2" class="size-3.5" />
            <span>{{ trans('server/console.power_actions.restart') }}</span>
        </button>

        <button
            type="button"
            wire:click="mountAction('stop')"
            @disabled(! $component->canStop())
            class="overview-power-button overview-power-button--stop"
        >
            <x-filament::icon icon="tabler-player-stop-filled" class="size-3.5" />
            <span>{{ trans('server/console.power_actions.stop') }}</span>
        </button>
    </div>
@endif
