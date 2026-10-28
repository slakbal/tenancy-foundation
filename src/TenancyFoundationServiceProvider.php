<?php

namespace Slakbal\TenancyFoundation;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Console\InstallCommand;
use Slakbal\TenancyFoundation\Events\ContactRequestReceived;
use Slakbal\TenancyFoundation\Events\TenantCancelled;
use Slakbal\TenancyFoundation\Events\TenantRegistered;
use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;
use Slakbal\TenancyFoundation\Http\Responses\RegistrationResponse;
use Slakbal\TenancyFoundation\Jobs\DeleteDueTenants;
use Slakbal\TenancyFoundation\Listeners\NotifyAdminsOfContactRequest;
use Slakbal\TenancyFoundation\Listeners\SendCancellationNotification;
use Slakbal\TenancyFoundation\Listeners\SendWelcomeNotification;
use Slakbal\TenancyFoundation\Listeners\UpdateLastLogin;
use Slakbal\TenancyFoundation\Livewire\PublicContactForm;

class TenancyFoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy-foundation.php', 'tenancy-foundation');
        $this->mergeConfigSections(['models', 'notifications', 'redirects', 'cancellation', 'contact_requests']);
        $this->mergeConfigFrom(__DIR__.'/../config/filament-defaults.php', 'filament-defaults');

        $this->app->register(FilamentDefaultsServiceProvider::class);

        if (config('tenancy-foundation.register_auth_defaults')) {
            $this->registerAuthDefaults();
        }
    }

    /**
     * @param  array<int, string>  $sections
     */
    protected function mergeConfigSections(array $sections): void
    {
        $defaults = require __DIR__.'/../config/tenancy-foundation.php';

        foreach ($sections as $section) {
            config(["tenancy-foundation.{$section}" => array_merge($defaults[$section], config("tenancy-foundation.{$section}", []))]);
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
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tenancy-foundation');

        Livewire::component('public-contact-form', PublicContactForm::class);

        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(RegistrationResponseContract::class, RegistrationResponse::class);

        Event::listen(TenantRegistered::class, SendWelcomeNotification::class);
        Event::listen(TenantCancelled::class, SendCancellationNotification::class);
        Event::listen(ContactRequestReceived::class, NotifyAdminsOfContactRequest::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('tenancy-foundation.cancellation.schedule_deletion')) {
                $schedule->job(new DeleteDueTenants)
                    ->dailyAt(config('tenancy-foundation.cancellation.deletion_time'))
                    ->onOneServer()
                    ->withoutOverlapping();
            }

            if (config('tenancy-foundation.contact_requests.schedule_pruning')) {
                $schedule->command('model:prune', ['--model' => [config('tenancy-foundation.models.contact_request')]])
                    ->daily()
                    ->onOneServer();
            }
        });

        if (config('tenancy-foundation.register_last_login_listener')) {
            Event::listen(Login::class, UpdateLastLogin::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);

            $this->publishes([
                __DIR__.'/../config/tenancy-foundation.php' => config_path('tenancy-foundation.php'),
            ], 'tenancy-foundation-config');

            $this->publishes([
                __DIR__.'/../config/filament-defaults.php' => config_path('filament-defaults.php'),
            ], 'tenancy-foundation-defaults');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/tenancy-foundation'),
            ], 'tenancy-foundation-views');
        }
    }
}
