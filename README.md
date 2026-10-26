# Tenancy Foundation

Multi-tenancy foundation for Laravel 13 and Filament v5: subdomain tenants, tenant users, a separate admin guard, role/permission authorisation (spatie, teams), a tenant registration wizard, Filament panel plugins and opinionated Filament defaults.

- Two Filament panels: **admin** (path `/admin`, guard `admin`) and **tenant** (root path, guard `web`, one subdomain per tenant).
- Tenant users belong to one or more tenants; roles and permissions are scoped per tenant (spatie teams). Admin roles are global.
- Business logic lives in Action classes you can replace; every page, resource and model can be overridden from the application.

## Requirements

- PHP 8.3+, Laravel 13.17+, Filament 5
- `spatie/laravel-permission` 8 (installed as a dependency, configured by you, see step 4)
- A database, queue worker and mailer. Wildcard DNS and a wildcard TLS certificate for `*.your-domain` in production. Laravel Herd serves `*.project.test` automatically.

## Installation

### 1. Require the package

Until the package is on GitHub, use a local path repository in the application's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../tenancy-foundation", "options": { "symlink": true } }
],
"require": { "slakbal/tenancy-foundation": "@dev" }
```

Later, switch to `{ "type": "vcs", "url": "git@github.com:slakbal/tenancy-foundation.git" }` and a tagged constraint such as `^1.0`.

```bash
composer require slakbal/tenancy-foundation
php artisan tenancy-foundation:install
```

The install command publishes `config/tenancy-foundation.php`, warns if spatie teams are not enabled and prints the remaining steps.

### 2. Create your models

Extend the package models. The package works with them as they are; extend to add your own behaviour.

```php
namespace App\Models;

use Slakbal\TenancyFoundation\Models\User as BaseUser;

class User extends BaseUser {}
```

Do the same for `Admin`, `Tenant` and `Role` (`Slakbal\TenancyFoundation\Models\{Admin,Tenant,Role}`), then point the config at them in `config/tenancy-foundation.php`:

```php
'models' => [
    'tenant' => App\Models\Tenant::class,
    'user' => App\Models\User::class,
    'admin' => App\Models\Admin::class,
    'role' => App\Models\Role::class,
],
```

Set the auth provider model too: `config/auth.php` → `providers.users.model` = `App\Models\User::class`. The `admin` guard, `admins` provider and `admins` password broker are registered by the package (see [What the package registers](#what-the-package-registers)).

### 3. Environment

```dotenv
APP_URL=https://your-domain.test
APP_DOMAIN=your-domain.test
SESSION_DOMAIN=.your-domain.test
QUEUE_CONNECTION=database
```

- `APP_DOMAIN`: tenants resolve at `{subdomain}.APP_DOMAIN`. If it is empty, tenant URLs render as `tenant1.`.
- `SESSION_DOMAIN`: must be the domain with a leading dot. Login, registration and email verification run on the central domain, tenants on subdomains; without a wildcard session cookie users are sent back to the login page. Change `SESSION_COOKIE` once if browsers still hold old host-only cookies.
- `QUEUE_CONNECTION`: all package emails (welcome, verification, password reset) are queued, so run `php artisan queue:work`. With `sync` they send immediately.

### 4. Spatie permissions with teams

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

In `config/permission.php` set:

```php
'teams' => true,
'team_foreign_key' => 'tenant_id',
```

Do this before migrating, because the permission migration reads these values.

### 5. Migrate

```bash
php artisan migrate
```

The package migrations load automatically and run after Laravel's default `users` migration:

| Migration | Effect |
|---|---|
| `add_tenancy_columns_to_users_table` | Adds the missing tenancy columns to `users` (see [Users table](#users-table)) |
| `create_tenants_table` | The `tenants` table and the `tenant_user` membership pivot (`is_owner`, `is_default_tenant`) |
| `create_admin_users_table` | The `admins` table |

### 6. Register the panel plugins

Both panels use the package enums for ids, paths and guards. The ids matter (`admin` and `app`): impersonation and redirects build route names from them.

```php
// app/Providers/Filament/AdminPanelProvider.php
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;

return $panel
    ->id(FilamentPanel::ADMIN->getPanelId())
    ->path(FilamentPanel::ADMIN->getPath())
    ->authGuard(FilamentPanel::ADMIN->getAuthGuard())
    ->authPasswordBroker(FilamentPanel::ADMIN->getPasswordBroker())
    ->strictAuthorization()
    ->login()
    ->plugins([AdminPlugin::make()]);
```

```php
// app/Providers/Filament/TenantPanelProvider.php
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;

return $panel
    ->default()
    ->id(FilamentPanel::TENANT->getPanelId())
    ->path(FilamentPanel::TENANT->getPath())
    ->authGuard(FilamentPanel::TENANT->getAuthGuard())
    ->authPasswordBroker(FilamentPanel::TENANT->getPasswordBroker())
    ->strictAuthorization()
    ->login()
    ->plugins([TenantPlugin::make()]);
```

Keep the standard Filament panel middleware (`StartSession`, `AuthenticateSession`, `Authenticate` and so on) as in a normal panel provider. Do not add the tenant, tenant domain, registration, email verification or tenant middleware yourself; the plugins do it. Register both providers in `bootstrap/providers.php`.

### 7. Seed roles and the first admin

```php
// database/seeders/DatabaseSeeder.php
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;

$this->call([AdminRolePermissionsSeeder::class, TenantRolePermissionsSeeder::class]);
```

Roles are global; tenant roles are assigned per tenant. To add your own permission enums, extend `TenantRolePermissionsSeeder` and override `permissionEnums()` and `viewOnlyPermissions()`.

Create the first super admin:

```php
setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret'])
    ->assignRole(AdminRoles::SUPER_ADMIN);
```

### 8. Check it works

1. Open `https://your-domain.test/register`, register a tenant and verify the email.
2. You land on `https://{subdomain}.your-domain.test`.
3. Open `https://your-domain.test/admin` and sign in as the admin.

## What the package registers

- `admin` guard, `admins` provider and password broker, only if your `config/auth.php` does not define them (disable with `register_auth_defaults`).
- Filament `LogoutResponse` and `RegistrationResponse`. `redirects.logout_route` is a named route and defaults to `/`.
- `UpdateLastLogin` listener on `Login` (disable with `register_last_login_listener`).
- `TenantRegistered` event and the welcome email listener.
- Filament defaults from `config/filament-defaults.php` (toggle with `enabled`).
- Policies for `User`, `Admin` and `Tenant`, resolved per panel.
- User resources for both panels; the admin one has an impersonate action.

## Registration

The tenant panel exposes `/register`, a two-step wizard: tenant name and subdomain, then the owner account (`user_name_attributes`, email, password). `RegisterTenantOwner` creates the user, the tenant, the owner membership and the owner role in one transaction. Filament then sends the standard verification email and redirects to the verification prompt, and to the tenant domain once verified. `CreateTenant` is reused by the `RegisterTenant` page for signed-in users without a tenant.

- **Override the forms**: extend `RegisterUser` (and `RegisterTenant`) and override `getTenantFormComponents()` and/or `getUserFormComponents()`. Extra attributes must be fillable on the models. The page width is the `$maxWidth` property.
- **Register your pages**: `TenantPlugin::make()->usingRegisterUserPage(...)` and `->usingRegisterTenantPage(...)`.
- **Event and welcome email**: `TenantRegistered` (tenant, owner) is dispatched after the registration commits. `tenancy-foundation.notifications.welcome` (default `WelcomeToTenant`, `null` disables it) is mailed to the owner. Point it at your own notification class to customise it.
- **Reserved subdomains**: `tenancy-foundation.reserved_subdomains`.
- **Business logic**: bind your own subclass of `RegisterTenantOwner` or `CreateTenant` in the container.
- **Email verification** is enabled by the tenant plugin; the base `User` implements `MustVerifyEmail`.

## Customising

- **Models**: extend them and set `tenancy-foundation.models`. `Model::factory()` resolves the configured model.
- **Policies**: declare `App\Policies\{Admin|Tenant}\{Model}Policy` to override a package policy. The base class is `Policies\AuthorisationPolicy`.
- **Resources and pages**: `usingUserResource()`, `usingRegisterUserPage()`, `usingRegisterTenantPage()` and `usingEditTenantProfilePage()` on the plugins.
- **Translations**: JSON sentence keys loaded from the package `lang/en.json`. Override them in the application's `lang/en.json`.
- **Notifications**: always implement `ShouldQueue` on your own.

## Name attributes

The package has no hard-coded name columns. `tenancy-foundation.user_name_attributes` (default `['name']`, for example `['first_name', 'last_name']`) drives the admins migration, the users migration, the factories, the register form and the name table column.

Declare `#[Fillable]` and any name accessor on your own `User` and `Admin` models.

## Users table

Required columns: `ulid`, the name columns in `user_name_attributes`, `email`, `password`, `email_verified_at`, `last_login_at`, `last_login_ip`, `remember_token`, timestamps and soft deletes.

`add_tenancy_columns_to_users_table` adds whichever of `ulid` (backfilled for existing rows), the name attributes, `last_login_at`, `last_login_ip`, `deleted_at` and the `created_at` index are missing, in their original column order (MySQL and MariaDB only). It is a no-op for columns that already exist. A non-default name attribute is added as a nullable column; dropping the default `name` column is left to your application.

## Troubleshooting

| Symptom | Cause |
|---|---|
| Tenant URL is `https://tenant1./` | `APP_DOMAIN` is not set |
| Login succeeds but you are asked to log in again on the tenant | `SESSION_DOMAIN` is not `.your-domain`, or old cookies; change `SESSION_COOKIE` |
| Verification and welcome emails never arrive | Queue worker is not running |
| `Target class ...Seeders\... does not exist` | Run `composer update slakbal/tenancy-foundation` so the autoload map refreshes |
| Spatie team errors on roles | `permission.teams` and `team_foreign_key = tenant_id` were not set before migrating |

## Testing

```bash
composer install
vendor/bin/pest
```

Tests run on Orchestra Testbench with sqlite in memory and two test panels that register the plugins.
