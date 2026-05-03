<?php

namespace App\Enums;

use BackedEnum;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ServerState: string implements HasColor, HasIcon, HasLabel
{
    case Installing = 'installing';
    case InstallFailed = 'install_failed';
    case ReinstallFailed = 'reinstall_failed';
    case Suspended = 'suspended';
    case RestoringBackup = 'restoring_backup';

    public function getIcon(): BackedEnum
    {
        return match ($this) {
            self::Installing => TablerIcon::HeartBolt,
            self::InstallFailed, self::ReinstallFailed => TablerIcon::HeartX,
            self::Suspended => TablerIcon::HeartCancel,
            self::RestoringBackup => TablerIcon::HeartUp,
        };
    }

    public function getColor(bool $hex = false): string
    {
        if ($hex) {
            // brand state colours per DESIGN_SYSTEM.md, install and restore
            // are in flight transit states so they reuse the hearth tone,
            // suspended is honey amber, install failures are brick red.
            return match ($this) {
                self::Installing, self::RestoringBackup => '#EE600A',
                self::Suspended => '#D89020',
                self::InstallFailed, self::ReinstallFailed => '#B43C3C',
            };
        }

        return match ($this) {
            self::Installing => 'primary',
            self::InstallFailed => 'danger',
            self::ReinstallFailed => 'danger',
            self::Suspended => 'warning',
            self::RestoringBackup => 'primary',
        };
    }

    public function getLabel(): string
    {
        return str($this->value)->headline();
    }
}
