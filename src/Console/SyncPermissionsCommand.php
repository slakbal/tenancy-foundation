<?php

namespace Slakbal\TenancyFoundation\Console;

use Illuminate\Console\Command;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Support\Permissions\TenantPermissionEnums;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'tenancy-foundation:sync-permissions';

    protected $description = 'Create the permission rows the enums define, without touching role assignments, and report orphaned rows';

    public function handle(PermissionRegistrar $registrar): int
    {
        $guards = [
            FilamentPanel::ADMIN->getAuthGuard() => app(AdminRolePermissionsSeeder::class)->permissionNames(),
            FilamentPanel::TENANT->getAuthGuard() => app(TenantPermissionEnums::class)->names(),
        ];

        $permissionClass = $registrar->getPermissionClass();

        foreach ($guards as $guard => $names) {
            $created = 0;

            foreach ($names as $name) {
                $permission = $permissionClass::findOrCreate(name: $name, guardName: $guard);
                $created += (int) $permission->wasRecentlyCreated;
            }

            $orphans = $permissionClass::query()
                ->where('guard_name', $guard)
                ->whereNotIn('name', $names)
                ->pluck('name')
                ->all();

            $this->components->info("{$guard}: {$created} created, ".(count($names) - $created).' already present, '.count($orphans).' orphaned.');

            if ($orphans !== []) {
                $this->components->warn("Orphaned {$guard} permissions (no enum case, not deleted): ".implode(', ', $orphans));
            }
        }

        $registrar->forgetCachedPermissions();

        $this->components->info('Role assignments were not changed. Grant new permissions to roles yourself.');

        return self::SUCCESS;
    }
}
