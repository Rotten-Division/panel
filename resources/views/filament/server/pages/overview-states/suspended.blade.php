@php
    /** @var \App\Models\Server $server */
    $suspendedAt = $server->updated_at;
    $archivedBytes = app(\App\Contracts\Servers\StashedArchiveLocator::class)->archivedBytesFor($server);
    $dataRetained = $archivedBytes
        ? ($archivedBytes >= 1024 ** 3
            ? number_format($archivedBytes / (1024 ** 3), 2).' GiB'
            : number_format($archivedBytes / (1024 ** 2), 1).' MiB')
        : '—';
@endphp

<x-overview.stage-hero variant="suspended">
    <x-slot:illustration>
        <img src="{{ asset('images/state-icons/unauthorised.png') }}" alt="" aria-hidden="true" />
    </x-slot:illustration>

    <x-slot:title>Service suspended.</x-slot:title>

    <x-slot:body>
        Suspended by OspiteHosting on {{ $suspendedAt?->format('M j') }} pending review. The server is off-node and can't be started, reached or modified. Stored data is retained in full.
    </x-slot:body>

    <x-slot:cta>
        <a href="{{ config('app.support_url') }}" target="_blank" rel="noopener noreferrer" class="overview-btn overview-btn--primary overview-btn--lg">
            <x-filament::icon icon="tabler-message-circle" class="size-4" />
            <span>Contact support</span>
        </a>
        <button type="button" wire:click="mountAction('reviewSuspensionNotice')" class="overview-btn overview-btn--lg">
            <x-filament::icon icon="tabler-help-circle" class="size-4" />
            <span>Review suspension notice</span>
        </button>
    </x-slot:cta>
</x-overview.stage-hero>

<x-overview.fact-cards :cards="[
    [
        'label' => 'Suspended',
        'value' => $suspendedAt ? $suspendedAt->format('M j, H:i').' · '.$suspendedAt->diffForHumans() : '—',
        'icon' => 'tabler-lock',
        'variant' => 'warn',
    ],
    ['label' => 'Case reference', 'value' => '—', 'icon' => 'tabler-user-shield'],
    ['label' => 'Data retained', 'value' => $dataRetained, 'icon' => 'tabler-archive'],
]" class="mt-4" />

<x-overview.reason-card
    title="Reason for suspension"
    sub="Issued by OspiteHosting Trust & Safety"
    note="To respond, reply to the case email on file or open a ticket with support referencing this server's name."
>
    Your host has suspended this server pending review. A full explanation has been sent to the email address on the account; it is not attached to this in-panel notice yet. Reach out to support for the details.
</x-overview.reason-card>

<p class="overview-footnote">
    Suspensions are lifted only after review by OspiteHosting Trust & Safety. Stored data is retained for the duration of the suspension. If the case remains unresolved after 30 days, an account closure notice will be issued with export instructions before any data is removed.
</p>
