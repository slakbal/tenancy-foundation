<?php

namespace Slakbal\TenancyFoundation;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse as LogoutResponseContract;
use Filament\Auth\Http\Responses\Contracts\RegistrationResponse as RegistrationResponseContract;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Console\InstallCommand;
use Slakbal\TenancyFoundation\Events\Admin\AdminUserCreated;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestAnswered;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestReceived;
use Slakbal\TenancyFoundation\Events\Invitation\InvitationIssued;
use Slakbal\TenancyFoundation\Events\Tenant\TenantCancelled;
use Slakbal\TenancyFoundation\Events\Tenant\TenantDeleting;
use Slakbal\TenancyFoundation\Events\Tenant\TenantReactivated;
use Slakbal\TenancyFoundation\Events\Tenant\TenantRegistered;
use Slakbal\TenancyFoundation\Http\Middleware\EnsureUserHasTenant;
use Slakbal\TenancyFoundation\Http\Responses\LogoutResponse;
use Slakbal\TenancyFoundation\Http\Responses\RegistrationResponse;
use Slakbal\TenancyFoundation\Jobs\DeleteDueTenants;
use Slakbal\TenancyFoundation\Listeners\Admin\ActivateAdminOnPasswordReset;
use Slakbal\TenancyFoundation\Listeners\Admin\SendAdminInvitation;
use Slakbal\TenancyFoundation\Listeners\Auth\UpdateLastLogin;
use Slakbal\TenancyFoundation\Listeners\Contact\NotifyAdminsOfContactRequest;
use Slakbal\TenancyFoundation\Listeners\Contact\SendContactResponseNotification;
use Slakbal\TenancyFoundation\Listeners\Invitation\SendInvitationNotification;
use Slakbal\TenancyFoundation\Listeners\Tenant\CancelledTenant;
use Slakbal\TenancyFoundation\Listeners\Tenant\DeletedTenant;
use Slakbal\TenancyFoundation\Listeners\Tenant\SendCancellationNotification;
use Slakbal\TenancyFoundation\Listeners\Tenant\SendReactivationNotification;
use Slakbal\TenancyFoundation\Listeners\Tenant\SendWelcomeNotification;
use Slakbal\TenancyFoundation\Livewire\PublicContactForm;
use Slakbal\TenancyFoundation\Observers\RoleObserver;

class TenancyFoundationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/tenancy-foundation.php', 'tenancy-foundation');
        $this->mergeConfigSections(['models', 'notifications', 'redirects', 'cancellation', 'contact_requests', 'features', 'invitations']);
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
                'table' => 'admin_password_reset_tokens',
                'expire' => 60,
                'throttle' => 60,
            ]),
        ]);
    }

    public function boot(): void
    {
        $this->bootResources();
        $this->bootBindings();
        $this->bootMiddlewarePriority();
        $this->bootEventListeners();
        $this->bootSchedule();
        $this->bootConsole();
    }

    protected function bootResources(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadJsonTranslationsFrom(__DIR__.'/../lang');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'tenancy-foundation');

        Livewire::component('public-contact-form', config('tenancy-foundation.contact_requests.public_form', PublicContactForm::class));

        $this->bootPublicContactRoute();
    }

    protected function bootPublicContactRoute(): void
    {
        $path = config('tenancy-foundation.contact_requests.public_path');
        $domain = config('tenancy-foundation.domain');

        if (blank($path) || blank($domain)) {
            return;
        }

        Route::middleware('web')->domain($domain)->group(function () use ($path): void {
            Route::view($path, 'tenancy-foundation::contact')->name('public.contact');
        });
    }

    protected function bootBindings(): void
    {
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
        $this->app->bind(RegistrationResponseContract::class, RegistrationResponse::class);
    }

    protected function bootMiddlewarePriority(): void
    {
        $kernel = $this->app->make(HttpKernel::class);

        // Filament's Authenticate would answer a user of only cancelled tenants with a 403 before our sign-out redirect could run.
        if (method_exists($kernel, 'addToMiddlewarePriorityBefore')) {
            $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, EnsureUserHasTenant::class);
        }
    }

    protected function bootEventListeners(): void
    {
        Event::listen(TenantRegistered::class, SendWelcomeNotification::class);
        Event::listen(TenantCancelled::class, SendCancellationNotification::class);
        Event::listen(TenantReactivated::class, SendReactivationNotification::class);
        Event::listen(AdminUserCreated::class, SendAdminInvitation::class);
        Event::listen(PasswordReset::class, ActivateAdminOnPasswordReset::class);
        Event::listen(ContactRequestReceived::class, NotifyAdminsOfContactRequest::class);
        Event::listen(ContactRequestAnswered::class, SendContactResponseNotification::class);

        if (config('tenancy-foundation.features.invitations')) {
            Event::listen(InvitationIssued::class, SendInvitationNotification::class);
            config('tenancy-foundation.models.role')::observe(RoleObserver::class);
        }

        if (config('tenancy-foundation.register_last_login_listener')) {
            Event::listen(Login::class, UpdateLastLogin::class);
        }

        Event::listen(TenantCancelled::class, CancelledTenant::class);
        Event::listen(TenantDeleting::class, DeletedTenant::class);
    }

    protected function bootSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (config('tenancy-foundation.cancellation.schedule_deletion')) {
                $schedule->job(new DeleteDueTenants)
                    ->dailyAt(config('tenancy-foundation.cancellation.deletion_time'))
                    ->onOneServer()
                    ->withoutOverlapping();
            }

            if (config('tenancy-foundation.schedule_pruning')) {
                $schedule->command('model:prune')
                    ->daily()
                    ->onOneServer()
                    ->withoutOverlapping();

                $schedule->command('model:prune', ['--model' => $this->prunableModels()])
                    ->daily()
                    ->onOneServer()
                    ->withoutOverlapping();
            }
        });
    }

    /**
     * @return array<int, class-string>
     */
    protected function prunableModels(): array
    {
        return array_filter([
            config('tenancy-foundation.models.contact_request'),
            config('tenancy-foundation.features.invitations') ? config('tenancy-foundation.models.invitation') : null,
        ]);
    }

    protected function bootConsole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

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

        $this->publishes([
            __DIR__.'/../stubs/app/DeletedTenant.stub' => app_path('Listeners/Tenant/DeletedTenant.php'),
            __DIR__.'/../stubs/app/CancelledTenant.stub' => app_path('Listeners/Tenant/CancelledTenant.php'),
            __DIR__.'/../stubs/app/RegisteredTenant.stub' => app_path('Listeners/Tenant/RegisteredTenant.php'),
            __DIR__.'/../stubs/app/ReactivatedTenant.stub' => app_path('Listeners/Tenant/ReactivatedTenant.php'),
        ], 'tenancy-foundation-stubs');
    }
}
