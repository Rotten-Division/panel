{{-- phase 5 task 4 stub: filled in next commit. --}}
@php($transientTitle = match ($containerStatus ?? null) {
    \App\Enums\ContainerStatus::Stopping => 'Stopping',
    \App\Enums\ContainerStatus::Restarting => 'Restarting',
    default => 'Starting up',
})
<x-overview.state-banner
    variant="transient"
    :title="$transientTitle"
    subtitle="Wings is bringing the container online"
    icon="tabler-loader-2"
    :show-progress="true"
/>
