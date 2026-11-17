<?php

namespace Slakbal\TenancyFoundation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'tenancy-foundation:install';

    protected $description = 'Publish the tenancy foundation config, then offer to migrate, seed the roles and create the first admin';

    public function handle(): int
    {
        $this->publishConfig();

        $teamsEnabled = (bool) config('permission.teams');

        if (! $teamsEnabled) {
            $this->components->warn("Set 'teams' => true in config/permission.php (team_foreign_key: tenant_id) before running the permission migration.");
        }

        $this->components->bulletList([
            'Point config/tenancy-foundation.php models at your App\\Models subclasses.',
            'Set APP_DOMAIN (or ADMIN_DOMAIN) in .env.',
            'Register AdminPlugin / TenantPlugin in your Filament panel providers.',
            'Optional: php artisan vendor:publish --tag=tenancy-foundation-stubs for the app listeners that react to a tenant being registered, cancelled, reactivated or deleted.',
            'After adding permissions (package upgrade or your own enums) run php artisan tenancy-foundation:sync-permissions. It creates the missing rows and reports orphans, but never assigns them to a role.',
        ]);

        if ($teamsEnabled && $this->input->isInteractive()) {
            $this->offerSetupSteps();

            return self::SUCCESS;
        }

        $this->components->bulletList([
            'Run php artisan migrate.',
            'Seed roles with the package AdminRolePermissionsSeeder and your tenant_role_permissions seeder. Seeding applies role defaults on first install only; re-running it never resets a role that already has permissions.',
            'Invite the first super admin with php artisan tenancy-foundation:create-admin.',
        ]);

        return self::SUCCESS;
    }

    protected function publishConfig(): void
    {
        $exists = File::exists(config_path('tenancy-foundation.php'));

        if ($exists && (! $this->input->isInteractive() || ! confirm('config/tenancy-foundation.php already exists. Overwrite it with the package defaults?', default: false))) {
            $this->components->info('Kept the existing config/tenancy-foundation.php');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'tenancy-foundation-config', '--force' => $exists]);
        $this->components->info('Published config/tenancy-foundation.php');
    }

    protected function offerSetupSteps(): void
    {
        $this->components->info('Do the steps above first: the next ones use the configured models.');

        if (confirm('Run the migrations now?', default: false)) {
            $this->call('migrate', ['--force' => true]);
        }

        if (confirm('Seed the admin and tenant roles now?', default: false)) {
            $this->call('db:seed', ['--class' => AdminRolePermissionsSeeder::class, '--force' => true]);
            $this->call('db:seed', ['--class' => config('tenancy-foundation.seeders.tenant_role_permissions'), '--force' => true]);
        }

        if (confirm('Invite the first admin now?', default: false)) {
            $this->call('tenancy-foundation:create-admin');
        }
    }
}
