<?php

namespace Slakbal\TenancyFoundation\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'tenancy-foundation:install';

    protected $description = 'Publish the tenancy foundation config and print the remaining setup steps';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'tenancy-foundation-config']);
        $this->components->info('Published config/tenancy-foundation.php');

        if (! config('permission.teams')) {
            $this->components->warn("Set 'teams' => true in config/permission.php (team_foreign_key: tenant_id) before running the permission migration.");
        }

        $this->components->bulletList([
            'Point config/tenancy-foundation.php models at your App\\Models subclasses.',
            'Set APP_DOMAIN in .env and run php artisan migrate.',
            'Register AdminPlugin / TenantPlugin in your Filament panel providers.',
            'Seed roles with the package AdminRolePermissionsSeeder and TenantRolePermissionsSeeder.',
        ]);

        return self::SUCCESS;
    }
}
