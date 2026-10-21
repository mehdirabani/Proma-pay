<?php

namespace Proma\Plugins\Repair\Support;

final class RepairAuthorization
{
    public static function isTechnician(): bool
    {
        return (string) \Auth::role() === 'repair_technician';
    }

    public static function can(string $permission): bool
    {
        return \PluginManager::can($permission);
    }
}
