<?php

namespace Slakbal\TenancyFoundation\Plugins;

use Asignua\FilamentCspNonce\CspNoncePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Enums\TenantResolution;
use Slakbal\TenancyFoundation\Exceptions\InvalidTenancyConfiguration;
use Slakbal\TenancyFoundation\Filament\Admin\Pages\ManageRolePermissions;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\AdminUserResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Users\UserResource;
use Slakbal\TenancyFoundation\Filament\Pages\EditProfile;
use Slakbal\TenancyFoundation\Filament\Pages\ResetPassword;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;
use Slakbal\TenancyFoundation\Support\PolicyResolver;
use Slakbal\TenancyFoundation\Support\SoleOwnerGuard;

class AdminPlugin implements Plugin
{
    protected string $userResource = UserResource::class;

    protected string $tenantResource = TenantResource::class;

    protected string $adminUserResource = AdminUserResource::class;

    protected string $contactRequestResource = ContactRequestResource::class;

    protected string $profilePage = EditProfile::class;

    protected string $rolePermissionsPage = ManageRolePermissions::class;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'tenancy-foundation-admin';
    }

    public function usingUserResource(string $resource): static
    {
        $this->userResource = $resource;

        return $this;
    }

    public function usingAdminUserResource(string $resource): static
    {
        $this->adminUserResource = $resource;

        return $this;
    }

    public function usingTenantResource(string $resource): static
    {
        $this->tenantResource = $resource;

        return $this;
    }

    public function usingContactRequestResource(string $resource): static
    {
        $this->contactRequestResource = $resource;

        return $this;
    }

    public function usingProfilePage(string $page): static
    {
        $this->profilePage = $page;

        return $this;
    }

    public function usingRolePermissionsPage(string $page): static
    {
        $this->rolePermissionsPage = $page;

        return $this;
    }

    public function register(Panel $panel): void
    {
        $panel->path(FilamentPanel::ADMIN->getPath());

        $domain = FilamentPanel::ADMIN->getDomain();

        throw_if($domain === null && TenantResolution::current() === TenantResolution::DOMAIN, InvalidTenancyConfiguration::missingAdminHost());

        if ($domain !== null) {
            $panel->domain($domain);
        }

        if (config('tenancy-foundation.features.csp')) {
            $panel->plugin(CspNoncePlugin::make());
        }

        $this->registerNavigationGroups($panel);

        $panel->navigationItems([
            $this->systemNavigationItem(__('Horizon'), 'horizon.index', Heroicon::OutlinedQueueList, 1),
            $this->systemNavigationItem(__('Log Viewer'), 'log-viewer.index', Heroicon::OutlinedDocumentText, 2),
        ]);

        $panel->databaseNotifications();

        if (config('tenancy-foundation.features.password_auth')) {
            $panel->passwordReset(resetAction: ResetPassword::class);
        }

        $panel->profile($this->profilePage);
        $panel->emailChangeVerification(config('tenancy-foundation.email_change_verification', true));
        $panel->authPasswordBroker(AuthGuard::ADMIN->getPasswordBroker());
        $panel->pages([$this->rolePermissionsPage]);
        $panel->resources([$this->userResource, $this->adminUserResource, $this->tenantResource, $this->contactRequestResource]);
    }

    // The panel sorts unlisted groups after the listed ones, so listing the package groups in their natural order and System last keeps System at the end. Groups the application lists itself stay in front.
    protected function registerNavigationGroups(Panel $panel): void
    {
        $labels = collect([$this->tenantResource, $this->userResource, $this->adminUserResource, $this->contactRequestResource, $this->rolePermissionsPage])
            ->filter(fn (string $component): bool => filled($component::getNavigationGroup()))
            ->sortBy(fn (string $component): int => $component::getNavigationSort() ?? 0)
            ->map(fn (string $component): string => (string) $component::getNavigationGroup())
            ->push(__('System'))
            ->unique()
            ->values();

        $existing = $panel->getNavigationGroups();
        $asObjects = Arr::first($existing) instanceof NavigationGroup;

        $panel->navigationGroups($labels->map(fn (string $label): NavigationGroup|string => $asObjects ? NavigationGroup::make($label) : $label)->all());
    }

    protected function systemNavigationItem(string $label, string $route, Heroicon $icon, int $sort): NavigationItem
    {
        return NavigationItem::make($label)
            ->group(__('System'))
            ->icon($icon)
            ->sort($sort)
            ->url(fn (): string => route($route), shouldOpenInNewTab: true)
            ->visible(fn (): bool => Route::has($route) && AdminAccountGuard::isSuperAdmin(Auth::guard(AuthGuard::ADMIN->getAuthGuard())->user()));
    }

    public function boot(Panel $panel): void
    {
        setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

        Gate::before(function ($user, $ability, $arguments = []): Response|bool|null {
            if ($denial = SoleOwnerGuard::denial($ability, $arguments) ?? AdminAccountGuard::denial($user, $ability, $arguments)) {
                return $denial;
            }

            return method_exists($user, 'hasRole') && $user->hasRole(AdminRoles::SUPER_ADMIN) ? true : null;
        });

        Gate::guessPolicyNamesUsing(fn (string $modelClass): string => PolicyResolver::resolve('Admin', $modelClass));
    }
}
