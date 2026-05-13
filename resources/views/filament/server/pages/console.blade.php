<x-filament-panels::page class="fi-console-page">
    @if ($nestNotice)
        {{-- inline nest notice instead of the global AlertBanner so the
             message stays scoped to the console page. phase F replaces this
             with the NestNotice component that also surfaces the wake button. --}}
        <div class="fi-section rounded-xl p-4 border border-warning-600/30 bg-warning-50/10">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="tabler-snowflake" class="size-5 text-warning-500 mt-0.5" />
                <div>
                    <p class="font-semibold text-warning-600 dark:text-warning-400">Warning</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $nestNotice }}</p>
                </div>
            </div>
        </div>
    @endif

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="$this->getWidgetData()"
        :widgets="$this->getVisibleWidgets()"
    />

    <x-filament-actions::modals />

</x-filament-panels::page>
