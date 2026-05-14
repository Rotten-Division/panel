<?php

namespace App\Filament\Admin\Pages;

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OverviewComponentsPreview extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::Beaker;

    protected static ?int $navigationSort = 100;

    protected static ?string $slug = '_design/components';

    protected string $view = 'filament.admin.pages.overview-components-preview';

    public static function getNavigationLabel(): string
    {
        return 'Overview components';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Diagnostics';
    }

    public function getTitle(): string
    {
        return 'Overview component preview';
    }

    public static function canAccess(): bool
    {
        return user()?->isRootAdmin() ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return user()?->isRootAdmin() ?? false;
    }

    protected function getViewData(): array
    {
        return [
            'pillVariants' => [
                ['transferring' => true, 'state' => null, 'container' => ContainerStatus::Running, 'caption' => 'transferring'],
                ['transferring' => false, 'state' => null, 'container' => ContainerStatus::Running, 'caption' => 'running (null state)'],
                ['transferring' => false, 'state' => null, 'container' => ContainerStatus::Offline, 'caption' => 'offline (null state)'],
                ['transferring' => false, 'state' => null, 'container' => ContainerStatus::Starting, 'caption' => 'starting (transient)'],
                ['transferring' => false, 'state' => ServerState::Installing, 'container' => null, 'caption' => 'installing'],
                ['transferring' => false, 'state' => ServerState::RestoringBackup, 'container' => null, 'caption' => 'restoring backup'],
                ['transferring' => false, 'state' => ServerState::Nest, 'container' => null, 'caption' => 'nest'],
                ['transferring' => false, 'state' => ServerState::Hydrating, 'container' => null, 'caption' => 'hydrating'],
                ['transferring' => false, 'state' => ServerState::Capturing, 'container' => null, 'caption' => 'capturing'],
                ['transferring' => false, 'state' => ServerState::Suspended, 'container' => null, 'caption' => 'suspended'],
                ['transferring' => false, 'state' => ServerState::InstallFailed, 'container' => null, 'caption' => 'install failed'],
            ],
            'bannerVariants' => [
                ['variant' => 'default', 'title' => 'Server is offline', 'subtitle' => 'Hit Start to bring it back up', 'progress' => false, 'icon' => null],
                ['variant' => 'transient', 'title' => 'Starting up', 'subtitle' => 'Wings is bringing the container online', 'progress' => true, 'icon' => 'tabler-loader-2'],
                ['variant' => 'installing', 'title' => 'Installing dependencies', 'subtitle' => 'First-run setup, usually under a minute', 'progress' => true, 'icon' => null],
                ['variant' => 'nest', 'title' => 'In cold storage', 'subtitle' => 'Wake the server to bring it back', 'progress' => false, 'icon' => null],
                ['variant' => 'suspended', 'title' => 'Account suspended', 'subtitle' => 'Contact support to restore access', 'progress' => false, 'icon' => null],
            ],
            'heroVariants' => [
                ['variant' => 'nest', 'title' => 'Resting in cold storage', 'body' => 'This server was moved to long-term storage to keep things tidy.'],
                ['variant' => 'rouse', 'title' => 'Waking up', 'body' => 'Pulling your server back online. Typically takes a minute or two.'],
                ['variant' => 'capture', 'title' => 'Capturing snapshot', 'body' => 'Saving your server to cold storage so it stays safe.'],
                ['variant' => 'suspended', 'title' => 'Account on hold', 'body' => 'Your account is suspended. Reach out to support to restore access.'],
            ],
            'factSamples' => [
                'three' => [
                    ['label' => 'CPU', 'value' => '2 cores', 'icon' => 'tabler-cpu'],
                    ['label' => 'Memory', 'value' => '4 GiB', 'icon' => 'tabler-stack-2'],
                    ['label' => 'Disk', 'value' => '20 GiB', 'icon' => 'tabler-database'],
                ],
                'two' => [
                    ['label' => 'Players', 'value' => '4 / 20', 'icon' => 'tabler-users'],
                    ['label' => 'Uptime', 'value' => '3d 14h', 'icon' => 'tabler-clock'],
                ],
                'warn' => [
                    ['label' => 'Disk usage', 'value' => '17.4 GiB / 20 GiB', 'icon' => 'tabler-database', 'variant' => 'warn'],
                ],
            ],
            'sampleAddress' => 'play.ospite.host:25565',
        ];
    }
}
