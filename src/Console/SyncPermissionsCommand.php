<?php

namespace Slakbal\TenancyFoundation\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Support\Permissions\AdminPermissionEnums;
use Slakbal\TenancyFoundation\Support\Permissions\TenantPermissionEnums;
use Spatie\Permission\PermissionRegistrar;

use function Laravel\Prompts\confirm;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'tenancy-foundation:sync-permissions';

    protected $description = 'Create the permission rows the enums define, without granting them to roles, and report orphaned rows (asks whether to delete them)';

    public function handle(PermissionRegistrar $registrar): int
    {
        $guards = [
            FilamentPanel::ADMIN->getAuthGuard() => app(AdminPermissionEnums::class)->names(),
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
                $this->handleOrphans($permissionClass, $guard, $orphans);
            }
        }

        $registrar->forgetCachedPermissions();

        $this->components->info('Role assignments were not changed. Grant new permissions to roles yourself.');

        return self::SUCCESS;
    }

    /**
     * @param  class-string<Model>  $permissionClass
     * @param  array<int, string>  $orphans
     */
    protected function handleOrphans(string $permissionClass, string $guard, array $orphans): void
    {
        $this->components->warn("Orphaned {$guard} permissions (no enum case): ".implode(', ', $orphans));

        if (! $this->input->isInteractive() || ! confirm("Delete these {$guard} permissions? Every role that holds them loses them.", default: false)) {
            $this->components->info('Orphaned permissions kept.');

            return;
        }

        $permissionClass::query()->where('guard_name', $guard)->whereIn('name', $orphans)->get()->each->delete();

        $this->components->info(count($orphans)." orphaned {$guard} permissions deleted.");
    }
}
