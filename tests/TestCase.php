<?php

namespace Slakbal\TenancyFoundation\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\TenancyFoundationServiceProvider;
use Slakbal\TenancyFoundation\Tests\Concerns\InteractsWithTenancy;
use Slakbal\TenancyFoundation\Tests\Support\AdminPanelProvider;
use Slakbal\TenancyFoundation\Tests\Support\TenantPanelProvider;
use Spatie\Permission\PermissionServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends Orchestra
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentServiceProvider::class,
            PermissionServiceProvider::class,
            TenancyFoundationServiceProvider::class,
            AdminPanelProvider::class,
            TenantPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('tenancy-foundation.domain', 'tenant.test');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('permission.teams', true);
        $app['config']->set('permission.team_foreign_key', 'tenant_id');
        $app['config']->set('tenancy-foundation.models.admin', Admin::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../stubs/testing');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
