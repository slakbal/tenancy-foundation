<?php

namespace Slakbal\TenancyFoundation\Listeners\Admin;

use Slakbal\TenancyFoundation\Events\Admin\RolePermissionsChanged;

class RecordRolePermissionsChange
{
    public function handle(RolePermissionsChanged $event): void
    {
        $change = new (config('tenancy-foundation.models.role_permission_change'));

        $change->forceFill([
            'role_id' => $event->roleId,
            'role_name' => $event->roleName,
            'guard_name' => $event->guardName,
            'added' => $event->added,
            'removed' => $event->removed,
        ]);

        if ($event->actor !== null) {
            $change->actor()->associate($event->actor);
        }

        $change->save();
    }
}
