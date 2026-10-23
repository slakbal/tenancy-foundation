# Tenancy Foundation

Multi-tenancy foundation for Laravel 13 and Filament v5: subdomain tenants, tenant users, a separate admin guard, role/permission authorisation (spatie, teams), Filament panel plugins, and opinionated Filament defaults.

## Installation

Until it lives on GitHub, use a local path repository in the project's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../tenancy-foundation", "options": { "symlink": true } }
],
"require": { "slakbal/tenancy-foundation": "@dev" }
```

Later switch to `{ "type": "vcs", "url": "git@github.com:slakbal/tenancy-foundation.git" }` and a tagged constraint such as `^1.0`.

```bash
composer require slakbal/tenancy-foundation
php artisan tenancy-foundation:install --users-migration
```

The install command publishes `config/tenancy-foundation.php`, optionally publishes the `users` migration, and warns if spatie `permission.teams` is not enabled (`team_foreign_key` must be `tenant_id`).

## Setup checklist

1. Create `App\Models\{User,Admin,Tenant,Role}` extending the package models, and point `config/tenancy-foundation.php` `models` at them.
2. Set `APP_DOMAIN` in `.env`. Tenants resolve at `{subdomain}.APP_DOMAIN`.
3. Publish spatie's permission migration with teams enabled, then `php artisan migrate`. Package migrations (`tenants`, `tenant_user`, `admins`) load automatically and run after the `users` table.
4. Register the plugins in your panel providers:

```php
// Admin panel
->id(FilamentPanel::ADMIN->getPanelId())
->authGuard(FilamentPanel::ADMIN->getAuthGuard())
->plugins([AdminPlugin::make()])

// Tenant panel
->id(FilamentPanel::TENANT->getPanelId())
->authGuard(FilamentPanel::TENANT->getAuthGuard())
->plugins([
    TenantPlugin::make()
        ->usingRegisterTenantPage(\App\Filament\Pages\RegisterTenant::class),
])
```

5. Seed roles: `AdminRolePermissionsSeeder` and `TenantRolePermissionsSeeder`. Extend the tenant seeder and override `permissionEnums()` / `viewOnlyPermissions()` to add your own permission enums.

## What the package registers

- `admin` guard, `admins` provider and password broker (only if your `config/auth.php` does not define them; disable with `register_auth_defaults`).
- Filament `LogoutResponse` / `RegistrationResponse`. Set `redirects.logout_route` to a named route; defaults to `/`.
- `UpdateLastLogin` listener on `Login` (disable with `register_last_login_listener`).
- Filament defaults (`config/filament-defaults.php`, toggle with `enabled`).

## Registration

The tenant panel exposes `/register`, a two-step wizard: tenant name and subdomain, then the owner account (`user_name_attributes`, email, password). `RegisterTenantOwner` creates the user, the tenant, the owner membership and the owner role in one transaction; Filament then sends the standard verification email and redirects to the verification prompt, and to the tenant domain once verified. `CreateTenant` is reused by the `RegisterTenant` page for signed-in users without a tenant.

- Override the form: extend `RegisterUser` (and `RegisterTenant` for signed-in users without a tenant) and override `getTenantFormComponents()` and/or `getUserFormComponents()`; extra attributes must be fillable on the models. The page width is the `$maxWidth` property. Register the page with `TenantPlugin::make()->usingRegisterUserPage(...)` / `usingRegisterTenantPage(...)`.
- `TenantRegistered` (tenant, owner) is dispatched after the registration commits. `tenancy-foundation.notifications.welcome` (default `WelcomeToTenant`, `null` disables it) is mailed to the owner by a listener; point it at your own notification class to customise.
- Reserved subdomains: `tenancy-foundation.reserved_subdomains`.
- Replace the business logic by binding your own subclass of `RegisterTenantOwner` or `CreateTenant` in the container.
- Email verification is enabled by the tenant plugin; the base `User` implements `MustVerifyEmail`.

## Customising

- **Models**: extend and set in `config('tenancy-foundation.models')`. Factories (`Model::factory()`) resolve the configured model.
- **Policies**: declare `App\Policies\{Admin|Tenant}\{Model}Policy` to override a package policy. Base class: `Policies\AuthorisationPolicy`.
- **Resources/pages**: `usingUserResource()`, `usingRegisterUserPage()`, `usingRegisterTenantPage()`, `usingEditTenantProfilePage()` on the plugins.
- **Translations**: JSON sentence keys, loaded from the package `lang/en.json`; override in the app's `lang/en.json`.

## Name attributes

The package has no hard-coded name columns. `config('tenancy-foundation.user_name_attributes')` drives the admins migration, the `users` stub, factories, the register form and the name table column. Declare `#[Fillable]` and any `name` accessor on your own `User`/`Admin` models.

## Required `users` columns

`ulid`, the name columns listed in `user_name_attributes` (default `name`; e.g. `['first_name', 'last_name']`), `email`, `password`, `email_verified_at`, `last_login_at`, `last_login_ip`, `remember_token`, timestamps, soft deletes. See `stubs/create_users_table.php.stub`.

## Testing

```bash
composer install
vendor/bin/pest
```

Tests run on Orchestra Testbench with sqlite in memory and two test panels registering the plugins.
