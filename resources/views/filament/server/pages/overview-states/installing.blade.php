{{-- phase 5 task 5 stub: filled in next commit. --}}
<x-overview.state-banner
    variant="installing"
    title="Setting up your server"
    :subtitle="'Installing ' . ($server->egg?->name ?? 'dependencies')"
    icon="tabler-tool"
    :show-progress="true"
/>
