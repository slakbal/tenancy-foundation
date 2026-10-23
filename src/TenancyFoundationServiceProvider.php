<?php

namespace Slakbal\TenancyFoundation;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Slakbal\TenancyFoundation\Console\InstallCommand;
use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;
use Slakbal\TenancyFoundation\Http\Responses\RegistrationResponse;
use Slakbal\TenancyFoundation\Listeners\UpdateLastLogin;

class TenancyFoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy-foundation.php', 'tenancy-foundation');
        $this->mergeConfigFrom(__DIR__.'/../config/filament-defaults.php', 'filament-defaults');

        $this->app->register(FilamentDefaultsServiceProvider::class);

        if (config('tenancy-foundation.register_auth_defaults')) {
            $this->registerAuthDefaults();
        }
    }

    protected function registerAuthDefaults(): void
    {
        $admin = config('tenancy-foundation.models.admin');

        config([
            'auth.guards.admin' => config('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']),
            'auth.providers.admins' => config('auth.providers.admins', ['driver' => 'eloquent', 'model' => $admin]),
            'auth.passwords.admins' => config('auth.passwords.admins', [
                'provider' => 'admins',
                'table' => config('auth.passwords.users.table', 'password_reset_tokens'),
                'expire' => 60,
                'throttle' => 60,
            ]),
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');

        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(RegistrationResponseContract::class, RegistrationResponse::class);

        if (config('tenancy-foundation.register_last_login_listener')) {
            Event::listen(Login::class, UpdateLastLogin::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../stubs/create_users_table.php.stub' => database_path('migrations/0001_01_01_000000_create_users_table.php'),
            ], 'tenancy-foundation-users-migration');

            $this->publishes([
                __DIR__.'/../config/tenancy-foundation.php' => config_path('tenancy-foundation.php'),
            ], 'tenancy-foundation-config');

            $this->publishes([
                __DIR__.'/../config/filament-defaults.php' => config_path('filament-defaults.php'),
            ], 'tenancy-foundation-defaults');
        }
    }
}
