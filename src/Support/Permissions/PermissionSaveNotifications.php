<?php

namespace Slakbal\TenancyFoundation\Support\Permissions;

use Filament\Notifications\Notification;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\Schemas\PermissionMatrixSchema;

/**
 * The notifications and headings the role permissions page shows around a save.
 */
class PermissionSaveNotifications
{
    public static function heading(RoleChange $change): string
    {
        return PermissionLabels::role($change->role->name).' ('.PermissionMatrixSchema::tabLabel($change->panel).')';
    }

    /**
     * @param  array<int, RoleChange>  $applied
     */
    public static function saved(array $applied): void
    {
        if ($applied === []) {
            Notification::make()->title(__('Nothing changed'))->body(__('The permissions already matched what you selected.'))->info()->send();

            return;
        }

        Notification::make()
            ->title(trans_choice('Permissions saved for :count role|Permissions saved for :count roles', count($applied)))
            ->body(implode(', ', array_map(fn (RoleChange $change): string => static::heading($change), $applied)))
            ->success()
            ->send();
    }

    /**
     * @param  array<string, string>  $failed
     */
    public static function failed(array $failed): void
    {
        foreach ($failed as $role => $message) {
            Notification::make()
                ->title(__('Could not save the :role role', ['role' => PermissionLabels::role($role)]))
                ->body($message)
                ->danger()
                ->send();
        }
    }
}
