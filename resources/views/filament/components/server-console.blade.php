<x-filament::widget>
    @assets
        @vite(['resources/css/console.css'])
    @endassets

    <div
        wire:ignore
        id="osconsole-slot"
        data-uuid="{{ $server->uuid }}"
        data-name="{{ $server->name }}"
        data-prelude="{{ str(config('app.name'))->slug()->lower() }}"
        data-font-size="{{ (int) (user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFontSize) ?: 14) }}"
        data-font-family="{{ (string) (user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFont) ?: 'monospace') }}"
        data-rows="{{ (int) (user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleRows) ?: 30) }}"
    ></div>

    @if ($this->authorizeSendCommand() && ! $readOnly)
        <div class="flex items-center w-full border-top overflow-hidden dark:bg-gray-900"
             style="border-bottom-right-radius: 10px; border-bottom-left-radius: 10px;">
            <x-filament::icon icon="tabler-chevrons-right" />
            <input
                id="send-command"
                class="w-full focus:outline-none focus:ring-0 border-none dark:bg-gray-900 p-1"
                type="text"
                aria-label="{{ trans('server/console.command') }}"
                :readonly="{{ $this->canSendCommand() ? 'false' : 'true' }}"
                placeholder="{{ $this->canSendCommand() ? trans('server/console.command') : trans('server/console.command_blocked') }}"
                wire:model="input"
                wire:keydown.enter="enter"
                wire:keydown.up.prevent="up"
                wire:keydown.down="down"
            >
        </div>
    @endif
</x-filament::widget>
