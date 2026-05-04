<?php

namespace App\Traits\Filament;

use App\Enums\HeaderActionPosition;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;

trait CanCustomizeHeaderActions
{
    /** @var array<Action|ActionGroup|CreateAction|DeleteAction> */
    protected static array $customHeaderActions = [];

    /** @var array<Action|ActionGroup> */
    protected static array $customTableHeaderActions = [];

    public static function registerCustomHeaderActions(HeaderActionPosition $position, Action|ActionGroup ...$customHeaderActions): void
    {
        static::$customHeaderActions[$position->value] = array_merge(static::$customHeaderActions[$position->value] ?? [], $customHeaderActions);
    }

    // table header actions land in the tables own toolbar slot, separate
    // from the page header. plugins that want their action sat next to the
    // tables search and filter chips register here, plugins that want a
    // page level action use registerCustomHeaderActions above.
    public static function registerCustomTableHeaderActions(Action|ActionGroup ...$customTableHeaderActions): void
    {
        static::$customTableHeaderActions = array_merge(static::$customTableHeaderActions, $customTableHeaderActions);
    }

    /** @return array<int,CreateAction> */
    protected function getDefaultHeaderActions(): array
    {
        return [];
    }

    /** @return array<Action|ActionGroup>
     * @throws Exception
     */
    protected function getHeaderActions(): array
    {
        return array_merge(
            static::$customHeaderActions[HeaderActionPosition::Before->value] ?? [],
            $this->getDefaultHeaderActions(),
            static::$customHeaderActions[HeaderActionPosition::After->value] ?? []
        );
    }

    /** @return array<Action|ActionGroup> */
    protected function getCustomTableHeaderActions(): array
    {
        return static::$customTableHeaderActions;
    }
}
