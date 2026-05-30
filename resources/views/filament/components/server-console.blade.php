<x-filament::widget>
    @assets
        @vite(['resources/js/console.js', 'resources/css/console.css'])
    @endassets

    <div
        wire:ignore
        x-data
        x-init="
            window.OspiteConsole.ensure(@js($server->uuid), {
                name: @js($server->name),
                fontSize: @js((int) (user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFontSize) ?: 14)),
                fontFamily: @js((string) (user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFont) ?: 'monospace')),
                rows: @js((int) (user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleRows) ?: 30)),
            });
            window.OspiteConsole.attach(@js($server->uuid), $el);
        "
        id="osconsole-slot"
        style="min-height: 12rem;"
    ></div>

    @if ($this->authorizeSendCommand() && ! $readOnly)
        <div class="flex items-center w-full border-top overflow-hidden dark:bg-gray-900"
             style="border-bottom-right-radius: 10px; border-bottom-left-radius: 10px;">
            <x-filament::icon icon="tabler-chevrons-right" />
            <input
                id="send-command"
                class="w-full focus:outline-none focus:ring-0 border-none dark:bg-gray-900 p-1"
                type="text"
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
