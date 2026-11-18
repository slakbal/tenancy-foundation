# Tenancy Foundation

`slakbal/tenancy-foundation`: the multi-tenancy base for Laravel 13 and Filament v5 apps. The companion app template is [`slakbal/filament-tenant-template`](https://github.com/slakbal/filament-tenant-template).

This README is the setup and usage guide. Release notes are in [CHANGELOG.md](CHANGELOG.md).

## Contents

- [What it provides](#what-it-provides)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration reference](#configuration-reference)
- [Domains, hosts and sessions](#domains-hosts-and-sessions)
- [Panels and guards](#panels-and-guards)
- [Models and traits](#models-and-traits)
- [Roles and permissions](#roles-and-permissions)
- [Registration](#registration)
- [Invitations](#invitations)
- [Tenant members](#tenant-members)
- [Cancelling and deleting a tenant](#cancelling-and-deleting-a-tenant)
- [Events and extension points](#events-and-extension-points)
- [Contact requests](#contact-requests)
- [Admin users](#admin-users)
- [Impersonation](#impersonation)
- [Developer sign in](#developer-sign-in)
- [Scheduled jobs](#scheduled-jobs)
- [Commands](#commands)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Upgrading](#upgrading)

## What it provides

- Two Filament panels, both set up by plugins:
  - **admin**: path `/admin` (`ADMIN_PATH`), optionally on its own host (`ADMIN_DOMAIN`), guard `admin`.
  - **tenant**: panel id `app`, root path, guard `web`, one subdomain per tenant (`{subdomain}.APP_DOMAIN`).
- Tenant users can belong to several tenants (`tenant_user` pivot with `is_owner`, `is_default_tenant` and `status`). Several owners per tenant are allowed. A tenant always keeps at least one owner.
- Roles and permissions come from spatie/laravel-permission with teams. Tenant roles are assigned per tenant (team = tenant id). Admin roles are assigned under team `0`. Role definitions are global.
- Tenant registration wizard, email verification, password reset and profile pages for both panels. Email change verification is optional.
- Admin resources: tenants (cancel and reactivate), tenant users (impersonate), admin users, contact requests, and a role permission matrix.
- Tenant resources: users (create, edit, roles, activate or deactivate, owners, remove, leave, restore) and invitations.
- Translations: English source strings with a complete German `lang/de.json`.
- Tenant lifecycle: cancellation with a grace period, reactivation and scheduled deletion. Each step dispatches an event.
- A public contact form, a tenant "Contact us" page, and contact request handling in the admin panel.
- Business logic lives in Action classes (`src/Actions/<Domain>`), which you can replace through the container. You can swap models, policies, pages and resources from the app.
- Opinionated Filament defaults (`config/filament-defaults.php`).

## Requirements

- PHP 8.3+ (`^8.3`), Laravel `^13.17`, Filament `^5.0`.
- Pulled in as dependencies: `spatie/laravel-permission` `^8.3` (you configure it, see [step 5](#5-spatie-permissions-with-teams)) and `stechstudio/filament-impersonate` `^5.6`.
- Suggested: `l3aro/filament-turnstile`, the captcha on the public contact form. Require it in your app (`composer require l3aro/filament-turnstile`) when the public contact page is on; without it the form has no captcha. Set `contact_requests.public_path` to `null` if you don't use the page.
- A database, a queue worker, a mailer and the scheduler (`schedule:run`).
- Wildcard DNS and a wildcard TLS certificate for `*.your-domain` in production. Locally, use a server that routes wildcard subdomains to the app ([Lerd](https://github.com/lerd-env/lerd) serves `*.project.test` automatically for a linked site).

## Installation

### 1. Require the package

During local development, use a path repository in the application's `composer.json` (the package checked out next to the app):

```json
"repositories": [
    { "type": "path", "url": "../tenancy-foundation", "options": { "symlink": true } }
],
"require": { "slakbal/tenancy-foundation": "@dev" }
```

To install from GitHub instead, use a VCS repository: `{ "type": "vcs", "url": "git@github.com:slakbal/tenancy-foundation.git" }`. There are no tags yet, so require `dev-main` until a version is tagged.

```bash
composer require slakbal/tenancy-foundation
php artisan tenancy-foundation:install
```

`tenancy-foundation:install` publishes `config/tenancy-foundation.php` (an existing one is kept unless you confirm overwriting it). It warns if spatie teams are off and prints the remaining steps below. Run interactively with teams on, it then offers to run the migrations, seed the roles (step 8) and invite the first admin; each question defaults to no.

Publish tags:

| Tag | Publishes |
|---|---|
| `tenancy-foundation-config` | `config/tenancy-foundation.php` (the install command does this) |
| `tenancy-foundation-defaults` | `config/filament-defaults.php` |
| `tenancy-foundation-views` | `resources/views/vendor/tenancy-foundation` (public contact page) |
| `tenancy-foundation-stubs` | App listeners in `app/Listeners/Tenant`: `RegisteredTenant`, `CancelledTenant`, `ReactivatedTenant`, `DeletedTenant`, `CreatedTenantUser` |

You don't need to republish the config after an upgrade. The package fills in missing nested keys from its own defaults (`models`, `notifications`, `redirects`, `cancellation`, `contact_requests`, `features`, `invitations`) and missing top-level keys.

### 2. Create your models

Extend the package models. They work as they are, so extend them only to add your own behaviour.

```php
namespace App\Models;

use Slakbal\TenancyFoundation\Models\User as BaseUser;

class User extends BaseUser {}
```

Do the same for the other models you need, for example `Admin`, `Tenant`, `Role` and `Invitation` (`Slakbal\TenancyFoundation\Models\*`). Then point the config at them:

```php
'models' => [
    'tenant' => App\Models\Tenant::class,
    'user' => App\Models\User::class,
    'admin' => App\Models\Admin::class,
    'role' => App\Models\Role::class,
    // contact_request, invitation, role_permission_change keep the package defaults unless you swap them
],
```

Set `config/auth.php` → `providers.users.model` to `App\Models\User::class`. The package registers the `admin` guard, the `admins` provider and the `admins` password broker (see [Panels and guards](#panels-and-guards)).

If spatie should use your role model, set `config/permission.php` → `models.role` to that model too.

### 3. Environment

```dotenv
APP_URL=https://your-domain.test
APP_DOMAIN=your-domain.test
SESSION_DOMAIN=.your-domain.test
QUEUE_CONNECTION=database
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```

See [Domains, hosts and sessions](#domains-hosts-and-sessions) and [Environment variables](#environment-variables).

### 4. Queue and scheduler

All package notifications are queued, so run `php artisan queue:work` (with `QUEUE_CONNECTION=sync` they send immediately). Tenant deletion and pruning run on the scheduler, so `schedule:run` must run every minute in production (see [Scheduled jobs](#scheduled-jobs)).

### Horizon and Log Viewer

The package requires `laravel/horizon` and `opcodesio/log-viewer` (Horizon needs a Redis queue connection) and wires them into the backoffice for every project:

- **Access:** only a signed in super admin (admin guard) can open either tool, in every environment, including local. The package sets `Horizon::auth()` and `LogViewer::auth()`; do not override them in the application.
- **Host:** both are served on the admin panel host (`admin.domain`, or `domain` in `domain` mode) so tenant hosts never expose them. The package fills `horizon.domain` and `log-viewer.route_domain` with that host when they are empty; an application value wins. Without an admin host they follow the application's own settings.
- **Navigation:** the admin panel gets a **System** group with a Horizon and a Log Viewer item that open in a new tab, shown to super admins only.
- **Scheduler:** `horizon:snapshot` runs every five minutes (Horizon metrics).
- **Application tweaks:** everything else is the application's configuration, for example `config/horizon.php` (publish it to change supervisors and queues), `config/log-viewer.php`, `HORIZON_PATH` and `after_commit` on the queue connections. Run `php artisan horizon` under Supervisor and `horizon:terminate` on deploy.

### 5. Spatie permissions with teams

```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```

In `config/permission.php`:

```php
'teams' => true,
'team_foreign_key' => 'tenant_id',
```

Set both before you migrate, because the permission migration reads these values.

### 6. Migrate

```bash
php artisan migrate
```

The package migrations load automatically (no publishing) and run after Laravel's default `users` migration:

| Migration | Effect |
|---|---|
| `add_tenancy_columns_to_users_table` | Adds the missing tenancy columns to `users` (see [Users table](#users-table)) |
| `create_tenants_table` | `tenants` (`ulid`, `name`, `subdomain`, `billing_email`, `access_policy`, `cancelled_at`, `scheduled_for_deletion_at`) and the `tenant_user` pivot (`is_owner`, `is_default_tenant`, `status`, `accepted_invite_at`) |
| `create_admin_users_table` | `admins` (name attributes, `status`, two factor columns, `locale`, last login, soft deletes) |
| `create_contact_requests_table` | `contact_requests` |
| `create_notifications_table` | Laravel `notifications` table, skipped if it already exists (admin bell) |
| `create_admin_password_reset_tokens_table` | Separate reset tokens for admins, so tokens never cross between an admin and a tenant user who share an email |
| `create_invitations_table` | `invitations`. Always runs, even with `features.invitations` off |
| `create_domain_rules_table` | `domain_rules`: per tenant allow/block rule for an email domain. Always runs |
| `create_role_permission_changes_table` | Audit log of role permission changes |

### 7. Register the panel plugins

Use the package enums for ids, paths and guards. The ids matter (`admin` and `app`), because impersonation, invitation links and redirects build route names from them.

```php
// app/Providers/Filament/AdminPanelProvider.php
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Widgets\AccountWidget;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;

return $panel
    ->id(FilamentPanel::ADMIN->getPanelId())
    ->authGuard(FilamentPanel::ADMIN->getAuthGuard())
    ->authPasswordBroker(FilamentPanel::ADMIN->getPasswordBroker())
    ->strictAuthorization()
    ->login()
    ->widgets([AccountWidget::class])
    // ->middleware([...]) and ->authMiddleware([Authenticate::class]) as in a normal panel provider
    ->plugins([AdminPlugin::make()]); // sets ->path() and ->domain() from `admin.*`
```

```php
// app/Providers/Filament/TenantPanelProvider.php
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Filament\Widgets\AccountWidget;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;

return $panel
    ->default()
    ->id(FilamentPanel::TENANT->getPanelId())
    ->path(FilamentPanel::TENANT->getPath())
    ->authGuard(FilamentPanel::TENANT->getAuthGuard())
    ->authPasswordBroker(FilamentPanel::TENANT->getPasswordBroker())
    ->strictAuthorization()
    ->login()
    ->widgets([AccountWidget::class])
    // ->middleware([...]) as in a normal panel provider
    ->authMiddleware([Authenticate::class], isPersistent: true)
    ->plugins([TenantPlugin::make()]);
```

Keep the standard Filament panel middleware (`StartSession`, `AuthenticateSession`, `Authenticate` and so on). Don't add the tenant, tenant domain, registration, email verification or tenant middleware yourself, because the plugins add them. Register both providers in `bootstrap/providers.php`.

`Slakbal\TenancyFoundation\Filament\Widgets\AccountWidget` replaces Filament's `AccountWidget`. It adds a role badge per role of the signed-in user.

### 8. Seed roles and the first admin

```php
// database/seeders/DatabaseSeeder.php
use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;

$this->call([AdminRolePermissionsSeeder::class, TenantRolePermissionsSeeder::class]);
```

If you registered your own tenant seeder subclass (see [App permissions](#app-permissions)), call that subclass instead.

Invite the first super admin (it asks for the email, the name attributes and the role, creates a `pending` admin and mails `AdminInvited`):

```bash
php artisan tenancy-foundation:create-admin
```

Or create an active one directly under the admin team id:

```php
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;

setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'secret'])
    ->assignRole(AdminRoles::SUPER_ADMIN);
```

`Admin` models default to `status = active`. Admins created through the admin panel start as `pending` (see [Admin users](#admin-users)).

### 9. Check it works

1. Open `https://your-domain.test/register`, register a tenant and verify the email.
2. You land on `https://{subdomain}.your-domain.test`.
3. Open `https://your-domain.test/admin` and sign in as the admin.

## Configuration reference

### `config/tenancy-foundation.php`

| Key | Default | Purpose |
|---|---|---|
| `domain` | `env('APP_DOMAIN')` | Root domain without scheme or port. In `subdomain` mode tenants live at `{subdomain}.domain`; in `domain` mode it is the optional central host |
| `resolution` | `env('TENANCY_RESOLUTION', 'subdomain')` | `subdomain` or `domain`. See [Domains, hosts and sessions](#domains-hosts-and-sessions) for routing and session side effects |
| `verify_subdomain_hosts` | `env('TENANCY_VERIFY_SUBDOMAIN_HOSTS', false)` | `subdomain` mode only: tenant panel answers on a subdomain only when it belongs to an active tenant (404 for unknown, reserved, foreign hosts; `CancelledTenantResponse` for cancelled). The central host is never checked. Needs `domain`. `domain` mode always verifies |
| `admin.domain` | `env('ADMIN_DOMAIN')` | Host of the admin panel. `null`: any host in `subdomain` mode, `domain` (`APP_DOMAIN`) in `domain` mode. Reserved for tenants |
| `admin.path` | `env('ADMIN_PATH', 'admin')` | Path prefix of the admin panel; `''` or `'/'` (no prefix) on its own host. Slashes are trimmed |
| `admin.hide_super_admins` | `env('ADMIN_HIDE_SUPER_ADMINS', false)` | `true` hides admins with the `super_admin` role from every admin who is not a super admin (global scope on `Admin` while an admin is signed in). Super admins see each other; sign in, commands, jobs and guests are not filtered |
| `models.tenant` | `Models\Tenant` | Tenant model |
| `models.user` | `Models\User` | Tenant panel user, also used by the `users` provider |
| `models.admin` | `Models\Admin` | Admin panel user, also used by the `admins` provider |
| `models.role` | `Models\Role` | Spatie role model (the `RoleObserver` is attached to it) |
| `models.contact_request` | `Models\ContactRequest` | Contact form submissions |
| `models.invitation` | `Models\Invitation` | Tenant invitations |
| `models.domain_rule` | `Models\DomainRule` | Email domain allow/block rules per tenant |
| `models.role_permission_change` | `Models\RolePermissionChange` | Audit rows written when role permissions change |
| `seeders.tenant_role_permissions` | `TenantRolePermissionsSeeder` | The seeder that defines the tenant permission enums. Must be the package seeder or a subclass |
| `user_name_attributes` | `['name']` | Name column(s), for example `['first_name', 'last_name']` |
| `email_change_verification` | `true` | Email changes on the profile page take effect after the new address confirms (both panels) |
| `features.invitations` | `true` | Invitations resource, accept page, invitation permissions and pruning |
| `features.multi_tenant_users` | `true` | `false` hides the "Owner of" and "Member of" columns of the user tables (each user has one tenant). Memberships and the data model are unchanged |
| `features.password_auth` | `true` | Password sign in. `false` removes password reset (both panels), hides the "Send password reset link" actions, turns off user registration and makes the admin invitation a sign in link |
| `features.registration` | `true` | User registration page of the tenant panel (needs `password_auth`) |
| `features.email_verification` | `true` | Email verification prompt and routes of the tenant panel. `false` also hides the "Verified" column and filter of the user and admin tables, the "Email verified" infolist entries and the "Mark email verified" action (the columns stay in the database) |
| `features.tenant_registration` | `env('TENANCY_TENANT_REGISTRATION')` (`null`) | "Register a tenant" page. `null` follows the resolution (on for `subdomain`, off for `domain`); `true` or `false` always forces it |
| `features.csp` | `env('TENANCY_CSP', false)` | Nonce based Content-Security-Policy on both panels through `asignua/filament-csp-nonce` (a dependency of this package; `AdminPlugin` and `TenantPlugin` register its plugin). Tune it in the app's `config/csp-nonce.php` (`php artisan vendor:publish --tag=csp-nonce-config`): `report_only` (env `CSP_REPORT_ONLY`; the dependency default enforces, start with `true`), `directives` for extra sources, the report endpoint `POST /csp/report` and its storage. Pages outside the panels add the `csp.nonce` middleware themselves. Run `php artisan view:clear` once after turning it on |
| `features.security_headers` | `env('TENANCY_SECURITY_HEADERS', false)` | Global middleware adding `X-Content-Type-Options`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy` to every response, plus `Strict-Transport-Security` in production over HTTPS |
| `features.dev_sign_in` | `env('TENANCY_DEV_SIGN_IN', false)` | Lets a login page that uses `HasDeveloperSignIn` show a local-only account picker and "Developer sign in" button. Only works when `APP_ENV` is `local` (404 elsewhere, whatever this says). See [Developer sign in](#developer-sign-in) |
| `response_headers` | `[]` | Extra response headers (`name => value`) added with `features.security_headers`. Merged over the defaults: the same name replaces one, `null` or `''` removes it |
| `invitations.expiry_days` | `env('INVITATION_EXPIRY_DAYS', 7)` | Days an invitation link stays valid |
| `invitations.retention_days` | `env('INVITATION_RETENTION_DAYS', 30)` | Pruning window for invitations |
| `reserved_subdomains` | `www, admin, app, api, mail, dev, test` | Subdomains the registration form refuses |
| `notifications.welcome` | `WelcomeToTenant` | Mailed to the new owner after registration |
| `notifications.cancelled` | `AccountCancelled` | Mailed to every owner on cancellation |
| `notifications.reactivated` | `AccountReactivated` | Mailed to every owner on reactivation |
| `notifications.admin_invited` | `AdminInvited` | Mailed to a new admin with a set password link (with `password_auth` off: a sign in link to the admin login page) |
| `notifications.contact_response` | `ContactRequestResponded` | Mailed to the requester when an admin responds |
| `notifications.contact_received` | `NewContactRequest` | Database notification (admin bell) for a new contact request |
| `notifications.member_removed` | `MemberRemoved` | Mailed to a user removed from a tenant (not when they left on their own) |
| `notifications.invitation_created` | `InvitationCreated` | Mailed to the invitee with a signed accept link |
| `cancellation.enabled` | `env('TENANT_CANCELLATION_ENABLED', true)` | `false` hides cancelling in the admin UI: cancel and reactivate actions (policy denies both) and the status, cancelled and deletion scheduled columns, entries and filters of the tenants resource. Existing cancelled tenants and the lifecycle code, middleware and daily job are unchanged |
| `cancellation.grace_days` | `env('TENANT_CANCELLATION_GRACE_DAYS', 30)` | Days between cancellation and deletion |
| `cancellation.schedule_deletion` | `true` | Registers the daily `DeleteDueTenants` job |
| `cancellation.deletion_time` | `'01:00'` | Time of day (server time) the deletion job runs |
| `contact_requests.prune_after_days` | `env('CONTACT_REQUEST_PRUNE_AFTER_DAYS', 90)` | Closed requests are pruned this many days after closing. `0` or `null` keeps them forever |
| `contact_requests.notify_admins` | `true` | Notify admins who may view contact requests (and super admins) about new requests |
| `contact_requests.public_path` | `'contact'` | Public contact page path on the main domain (route `public.contact`). `null` disables it |
| `contact_requests.public_form` | `PublicContactForm` | Livewire component behind the public page |
| `redirects.logout_route` | `null` | Named route used after logout (and after registering on a panel without tenancy). Falls back to `/`. Ignored on logout in `domain` mode |
| `schedule_pruning` | `true` | Registers the daily `model:prune` commands |
| `register_auth_defaults` | `true` | Registers the `admin` guard, the `admins` provider and the `admins` broker if `config/auth.php` lacks them |
| `register_last_login_listener` | `true` | Stores `last_login_at` and `last_login_ip` on `Login` |

Every `notifications.*` entry takes a class, or `null` to stop that notification. Your own notification classes should implement `ShouldQueue`, like the package ones.

### `config/filament-defaults.php`

| Key | Purpose |
|---|---|
| `enabled` | Master switch for all defaults |
| `translate_labels` | `translateLabel()` on fields, entries and columns |
| `action_defaults` + `actions.*` | Defaults for `Action`, `ActionGroup`, create, edit, view, delete, restore, force delete and bulk actions (one switch each) |
| `select_defaults` + `select.*` | `native`, `selectable_placeholder`, `searchable`, `preload` |
| `datetime_defaults` + `datetime.*` | `seconds`, `max_date` |
| `fileupload_defaults` | `FileUpload::moveFiles()` |
| `repeater_defaults` + `repeater.*` | Delete confirmation on `Repeater` and `Builder` |
| `form_defaults` + `form.*` | `toggle_buttons`, `text_input`, `textarea` |
| `page_defaults` + `page.*` | `validation_notification`, `sticky_form_actions` |
| `table_defaults` + `table.*` | `table`, `image_column`, `select_filter` |
| `format_defaults` + `formats.*` | Currency (`zar`), date, datetime and time display formats, number locale |
| `panel_defaults` + `panel.*` | `top_navigation` (`true`), `max_content_width` (`'container'`, `null` keeps Filament's `7xl`) |

A block switch gates all of its sub-items.

### Environment variables

| Variable | Used for |
|---|---|
| `APP_URL` | Central URL. Deleted-user redirects and the helpers `appUrl()`, `appDomainScheme()` and `appDomainHost()` use it |
| `APP_DOMAIN` | `tenancy-foundation.domain` |
| `TENANCY_RESOLUTION` | `tenancy-foundation.resolution`: `subdomain` (default) or `domain` |
| `TENANCY_VERIFY_SUBDOMAIN_HOSTS` | `tenancy-foundation.verify_subdomain_hosts`: `false` (default) or `true` |
| `ADMIN_DOMAIN`, `ADMIN_PATH` | `tenancy-foundation.admin.domain` / `admin.path`: admin panel host and path |
| `SESSION_DOMAIN` | `subdomain` mode: must be `.your-domain`. `domain` mode: ignored, the cookie is host-only (see below) |
| `SESSION_COOKIE` | Change it once to drop old host-only cookies |
| `QUEUE_CONNECTION` | Queued notifications |
| `TENANT_CANCELLATION_GRACE_DAYS` | `cancellation.grace_days` |
| `INVITATION_EXPIRY_DAYS`, `INVITATION_RETENTION_DAYS` | `invitations.*` |
| `CONTACT_REQUEST_PRUNE_AFTER_DAYS` | `contact_requests.prune_after_days` |
| `TENANCY_DEV_SIGN_IN` | `features.dev_sign_in`: `false` (default) or `true` (local only) |
| `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Captcha on the public contact form (only with `l3aro/filament-turnstile` installed) |

## Domains, hosts and sessions

`tenancy-foundation.resolution` (`TENANCY_RESOLUTION`) decides how the tenant panel finds the tenant from the request host. Both modes share the same `tenants` table: `subdomain` and `domain` are nullable unique columns and only the one for the active mode is used. `TenantResolution::current()` exposes the mode; `urlFor($tenant)` / `hostFor($tenant)` build tenant URLs (emails, redirects, admin links), so never concatenate hosts yourself.

### `subdomain` (default)

| Host | Serves |
|---|---|
| `APP_DOMAIN` | Tenant panel login, `/register`, email verification, password reset, `/admin` (any host unless `ADMIN_DOMAIN` is set), public contact page (`/contact`) |
| `ADMIN_DOMAIN` (optional) | The admin panel only (`/ADMIN_PATH`); its first label is reserved when it is a subdomain of `APP_DOMAIN` |
| `{subdomain}.APP_DOMAIN` | The tenant panel for that tenant (`tenantDomain('{tenant:subdomain}.'.domain)`, slug attribute `subdomain`) |

- `APP_DOMAIN`: if it's empty, tenant URLs render as `tenant1.` and the public contact route is not registered.
- `SESSION_DOMAIN`: set it to the domain with a leading dot. Login, registration and verification run on the central domain and tenants on subdomains. Without a wildcard session cookie, users are sent back to the login page. If browsers still hold old host-only cookies, change `SESSION_COOKIE` once.
- The tenant forms show the `subdomain` field (suffix `.APP_DOMAIN`, `reserved_subdomains` and the admin host's subdomain rejected).
- By default every subdomain shows the tenant login, also unknown and cancelled ones. Set `verify_subdomain_hosts` (`TENANCY_VERIFY_SUBDOMAIN_HOSTS=true`) to answer 404 on unknown, reserved and foreign hosts and the [cancelled tenant response](#cancelled-tenant-hosts) on a cancelled tenant's subdomain. `APP_DOMAIN` itself is never checked. The shared session is not touched there, because it also serves the user's other tenants.
- Local development: any server that routes wildcard subdomains of the app host to the app works. [Lerd](https://github.com/lerd-env/lerd) does this for `*.project.test` (`lerd link project && lerd secure`). Use the same host in `APP_URL`, `APP_DOMAIN` and `SESSION_DOMAIN` (with a leading dot), for example `https://myapp.test`, `myapp.test` and `.myapp.test`.

### `domain`

| Host | Serves |
|---|---|
| `APP_DOMAIN` (optional) | Central host: public contact page, and the admin panel when `ADMIN_DOMAIN` is empty. Never resolved as a tenant |
| `ADMIN_DOMAIN` (recommended) | The admin panel (backoffice) only, at `/ADMIN_PATH`. Never resolved as a tenant; staff sign in on this host only |
| `tenants.domain` (e.g. `academy.example.com`) | The tenant panel for that tenant, including its own login (`tenantDomain('{tenant:domain}')`, slug attribute `domain`) |

- Tenants can live on unrelated domains. Every tenant host must point at the app (DNS, TLS certificate, web server/Lerd site alias).
- Sessions: the package forces `session.domain` to `null`, so the session, CSRF and remember-me cookies are host-only and two tenants never share a session, even under a common parent domain. `SESSION_DOMAIN` is ignored. Users sign in on each tenant host separately. Because all hosts share one session store, the tenant panel middleware `BindSessionToHost` also records the host in the session; a session cookie copied to another host is invalidated there (guest, new CSRF token).
- Filament matches any host on the tenant routes (`{tenant}` allows dots); the package excludes `APP_DOMAIN` and the admin host from those routes after boot, so they are never taken as a tenant.
- Every tenant panel route, including login, password reset and the invitation accept page, answers only on the host of an existing, active tenant (`EnsureKnownTenantHost`, first in the tenant panel middleware): unknown hosts, `APP_DOMAIN` and the admin host get a 404; a cancelled tenant's host gets the [cancelled tenant response](#cancelled-tenant-hosts) (default 404, so it is indistinguishable from an unknown host) and any session on it is invalidated, so members are not signed in again when the tenant is reactivated. Invitation links point at the tenant's host with a signed path (`AcceptInvitation::signedUrl()`, `signed:relative`) and open only on that host (404 elsewhere).
- The admin panel is bound to `ADMIN_DOMAIN`, or to `APP_DOMAIN` when that is empty, so tenant hosts return 404 for the admin path. One of them is required: with both empty `AdminPlugin` throws `InvalidTenancyConfiguration` at boot instead of serving the admin panel on every tenant host.
- The tenant forms show the `domain` field: lower-case host name only (no scheme, path or port), unique, not `APP_DOMAIN` or the admin host.
- Sign in on a tenant host accepts only that tenant's active members, plus users with a pending invitation to it (they sign in there to accept, with `features.invitations` on). Anyone else gets Filament's "These credentials do not match our records", so the host does not reveal accounts of other tenants (`canAccessPanel()` in `HasTenantAccess`).
- After logout, users return to the login page of the panel on the same host (`redirects.logout_route` is not used), because each host is its own site.
- Impersonation crosses from the admin host to the tenant host with a signed, single-use, 60 second link (see [Impersonation](#impersonation)).

### Both modes

- After registration or verification, `RegistrationResponse` redirects to the default tenant URL (`urlFor`), or to tenant registration if the user has no tenant.
- After logout in `subdomain` mode, users go to `redirects.logout_route`, or to `/` if that is unset.
- Switching modes needs no migration, but every tenant needs a value in the new column. Change `SESSION_COOKIE` once when switching so browsers drop the old cookies.

## Panels and guards

| | Admin panel | Tenant panel |
|---|---|---|
| Enum | `FilamentPanel::ADMIN` | `FilamentPanel::TENANT` |
| Panel id / path | `admin` / `admin.path` (default `admin`) | `app` / `''` |
| Guard (`AuthGuard`) / broker | `admin` / `admins` | `web` / `users` |
| Plugin | `AdminPlugin` | `TenantPlugin` |
| Super user via `Gate::before` | `super_admin` role | owner of the current tenant |
| Policies | `Policies\Admin\*` | `Policies\Tenant\*` |

### What `AdminPlugin` registers

- Database notifications, password reset (`ResetPassword`), profile (`EditProfile`), email change verification, the `admins` broker.
- Resources: `Users\UserResource` (tenant users), `AdminUsers\AdminUserResource` (slug `admins`), `Tenants\TenantResource`, `ContactRequests\ContactRequestResource`.
- Page: `ManageRolePermissions` (Security > Role permissions).
- Navigation group **System** with **Horizon** and **Log Viewer** items that open in a new tab, visible to super admins only (see [Horizon and Log Viewer](#horizon-and-log-viewer)).
- On boot: `setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID)`, a `Gate::before` (`SoleOwnerGuard`, then `AdminAccountGuard`, then `super_admin` → allow), and policy resolution for the `Admin` namespace.

Overrides: `usingUserResource()`, `usingAdminUserResource()`, `usingTenantResource()`, `usingContactRequestResource()`, `usingProfilePage()`, `usingRolePermissionsPage()`.

### What `TenantPlugin` registers

- Tenancy on `models.tenant` (slug attribute `subdomain`) with the tenant domain, tenant registration (`RegisterTenant`) and tenant profile (`EditTenantProfile`, slug `tenant-profile`, owner only).
- Registration (`RegisterUser`), password reset, profile, email change verification, email verification (`EmailVerificationPrompt`), the `users` broker.
- Tenant middleware (persistent): `SyncTenantPermissionsContext` (sets the spatie team id to the current tenant), `EnsureTenantIsNotCancelled`.
- Panel middleware (persistent): `RedirectDeletedUserHome`, `EnsureUserHasTenant` (signs out users with no tenant, only cancelled tenants, or no accessible tenant). The service provider gives `EnsureUserHasTenant` priority over Filament's `Authenticate`.
- Resources: `Users\UserResource`, and `Invitations\InvitationResource` plus the accept page route when `features.invitations` is on.
- Page: `ContactUs` (slug `contact`).
- A `HEAD_END` style that shows the role in the tenant switcher in the danger colour (`currentTenantLabelStyle()`, override to change).
- On boot: a `Gate::before` (`SelfDeletionGuard`, `SoleOwnerGuard`, `TenantMembershipGuard`, `TenantForceDeleteGuard`, then owner → allow) and policy resolution for the `Tenant` namespace.

Overrides: `usingUserResource()`, `usingRegisterUserPage()`, `usingRegisterTenantPage()`, `usingEditTenantProfilePage()`, `usingContactPage()`, `usingProfilePage()`, `usingInvitationResource()`, `usingAcceptInvitationPage()`.

### What the service provider registers

- The `admin` guard, the `admins` provider (`models.admin`) and the `admins` password broker (table `admin_password_reset_tokens`), each only if `config/auth.php` doesn't define it. Disable with `register_auth_defaults`.
- Filament `LogoutResponse` and `RegistrationResponse` bindings.
- The package event listeners (see [Events](#events)) and the `UpdateLastLogin` listener on `Login` (disable with `register_last_login_listener`).
- `RoleObserver` on `models.role` when invitations are on.
- Migrations, JSON translations (`lang/en.json`), views (namespace `tenancy-foundation`), the `public-contact-form` Livewire component and the `public.contact` route.
- The scheduled jobs (including `horizon:snapshot` every five minutes) and the commands.
- Access to Horizon and the Log Viewer (see below).
- `FilamentDefaultsServiceProvider` (Filament defaults).

## Models and traits

| Model | Notes |
|---|---|
| `User` | `FilamentUser`, `HasTenants`, `HasDefaultTenant`, `MustVerifyEmail`. Uses `HasRoles`, `IsTenantUser`, `HasUlidKey`, `SoftDeletes` |
| `Admin` | `FilamentUser`. Uses `HasRoles`, `IsAdminUser`, `CanImpersonate`, `HasUlidKey`, `SoftDeletes`. `PERMISSIONS_TEAM_ID = 0`, guard `admin`, `status` cast to `AdminStatus` |
| `Tenant` | `HasName`, `HasCurrentTenantLabel`. Uses `IsTenant`, `HasUlidKey` |
| `TenantUser` | Pivot. `status` cast to `MemberStatus` |
| `Role` | Extends the spatie role, `invitations()` |
| `Invitation` | `BelongsToTenant`, prunable, `pending()` and `expired()` scopes, `refreshToken()`, hides `token` |
| `ContactRequest` | Prunable |
| `RolePermissionChange` | Audit row, `actor()` morph |

| Trait | Gives you |
|---|---|
| `HasUlidKey` | `ulid` column filled automatically and used as the route key |
| `IsTenantUser` | `HasTenantAccess` + `HasTenantMemberships` + `OwnsTenants` |
| `HasTenantAccess` | `canAccessPanel()`, `canSignIn()` (hook, default `true`), `canAccessTenant()`, `getTenants()`, `getDefaultTenant()` |
| `HasTenantMemberships` | `tenants()`, `memberTenants()`, `memberStatus()`, `hasOnlyCancelledTenants()`, `hasNoAccessibleTenants()` |
| `OwnsTenants` | `ownedTenants()`, `isOwner(?$tenant)` (reads `tenant_user.is_owner`), `withSoleOwnerFlag` scope, `isSoleOwnerOfAnyTenant()`, `soleOwnedTenants()` |
| `IsTenant` | `cancelled_at` and `scheduled_for_deletion_at` casts, `status` attribute (`TenantStatus`), `isCancelled()`, `isDueForDeletion()`, `active()` and `dueForDeletion()` scopes, `canBeInvited()` (hook), `getCurrentTenantLabel()`, `owners()`, `members()` |
| `BelongsToTenant` | `tenant()` relation to `models.tenant` |
| `IsAdminUser` | `canAccessPanel()`: admin guard, status `active` or `pending`, not soft deleted |
| `CanImpersonate` | `canImpersonate()`: `impersonate.user` permission |

`Model::factory()` resolves the configured model. Factories and their states:

- `UserFactory`: `unverified()`.
- `AdminFactory`: `unverified()`, `inactive()`, `pending()`, `withTwoFactor()`.
- `TenantFactory`, `InvitationFactory`: no states.
- `ContactRequestFactory`: `fromTenant()`, `closed()`, `responded()`.

### Name attributes

The package has no hard-coded name columns. `user_name_attributes` (default `['name']`, for example `['first_name', 'last_name']`) drives the admins migration, the users migration, the factories, the register form and the name table column.

Declare `#[Fillable]` and any name accessor on your own `User` and `Admin` models.

The package reads `$user->name` for display (modal headings, notifications, record titles), so the user model must expose a `name` attribute. With custom `user_name_attributes`, add an accessor that joins them:

```php
protected function name(): Attribute
{
    return Attribute::get(function (): string {
        $name = collect(config('tenancy-foundation.user_name_attributes'))
            ->map(fn (string $attribute): mixed => $this->getAttribute($attribute))
            ->filter()
            ->implode(' ');

        return $name !== '' ? $name : (string) $this->email;
    });
}
```

### Users table

Required columns: `ulid`, the name columns in `user_name_attributes`, `email`, `password`, `email_verified_at`, `last_login_at`, `last_login_ip`, `remember_token`, timestamps and soft deletes.

`add_tenancy_columns_to_users_table` adds whichever of these are missing:

- `ulid` (backfilled for existing rows),
- the name attributes,
- `last_login_at` and `last_login_ip`,
- `deleted_at`,
- the `created_at` index.

Columns are added in their original order on MySQL and MariaDB. Existing columns are left alone. A non-default name attribute is added as a nullable column. Dropping the default `name` column is up to your application.

## Roles and permissions

### Roles and teams

| Guard | Roles (`enum`) | Team id |
|---|---|---|
| `admin` | `AdminRoles`: `super_admin`, `admin`, `support` | `Admin::PERMISSIONS_TEAM_ID` (`0`, never a tenant id, so no placeholder tenant is needed) |
| `web` | `TenantRoles`: `owner`, `admin`, `view_only`, `customer` | the tenant id, set per request by `SyncTenantPermissionsContext` |

Permission enums: `AdminPermissions` (impersonate, permissions, admin users, tenants, contact requests, users), `TenantPermissions` (users, tenant) and `InvitationPermissions` (only when `features.invitations` is on). Values are `resource.action`, for example `user.view_any`.

- `super_admin` and the tenant `owner` are locked (`isLocked()`). They carry no permission rows and pass every check through `Gate::before`. Owner-only areas, such as the tenant profile page, check ownership directly. The owner flag is `tenant_user.is_owner`, and `User::isOwner()` reads it.
- `TenantRoles::assignableOptions()` lists the roles you can assign in forms (everything except owner).
- The `Gate::before` guards run before the super-user bypass, so they apply to super admins and owners too:
  - `SelfDeletionGuard`: you can't delete your own account.
  - `SoleOwnerGuard`: you can't delete a user who is the only owner of a tenant.
  - `TenantMembershipGuard`: you can't delete a user who belongs to other tenants from the tenant panel.
  - `TenantForceDeleteGuard`: the tenant panel never force deletes.
  - `AdminAccountGuard`: admins can't delete, deactivate or re-role themselves or the last active super admin.

### Seeding

`AdminRolePermissionsSeeder` and `TenantRolePermissionsSeeder` always `findOrCreate` every permission row. They apply a role's default permissions only when the role has no permissions yet, which normally means a first install. They never reset a role that already holds a permission, so re-seeding never undoes edits.

| Role | Seeded defaults |
|---|---|
| admin `super_admin` | none (bypass) |
| admin `admin` | every admin permission except `permissions.manage` |
| admin `support` | view admins, tenants and users, send password reset, impersonate, view, update and respond to contact requests |
| tenant `owner` | none (always cleared, bypass) |
| tenant `admin` | every tenant permission (including invitation ones when enabled) |
| tenant `view_only` | `viewOnlyPermissions()`: `user.view_any`, `user.view` |
| tenant `customer` | same defaults as `view_only`; the role for customers who join a tenant themselves (for example on first SSO login), so the product can give them its own permissions |

The enums are the permission registry. After an upgrade, or after you add permissions to your own enums, run `php artisan tenancy-foundation:sync-permissions` (see [Commands](#commands)). It never grants anything, so assign new permissions to roles yourself, or on the Role permissions page.

### App permissions

An app adds its own tenant permissions by registering an enum with the tenant seeder:

1. Create a backed string enum whose values are `resource.action` names (for example `invoice.approve`). Labels fall back to a headline of the group and the action (`Invoice`, `Approve`).
2. Subclass the package seeder. Keep `parent::permissionEnums()`, because it carries the package enums and the invitations feature flag. You can also override `viewOnlyPermissions()` to change the view-only defaults.

```php
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;

class AppTenantRolePermissionsSeeder extends TenantRolePermissionsSeeder
{
    protected function permissionEnums(): array
    {
        return [...parent::permissionEnums(), InvoicePermissions::class];
    }
}
```

3. Point the config at the subclass:

```php
'seeders' => [
    'tenant_role_permissions' => App\Database\Seeders\AppTenantRolePermissionsSeeder::class,
],
```

4. Run `php artisan tenancy-foundation:sync-permissions`, then grant the new permissions on the Role permissions page.

The Role permissions page and the sync command read the tenant enums through `Support\Permissions\TenantPermissionEnums`, which resolves this config key. A class that isn't the package seeder or a subclass of it throws `Exceptions\InvalidPermissionSeeder`. Admin-side app permissions aren't supported, because `AdminPermissions` has no hook.

### Changing role permissions in code

`Actions\Roles\SyncRolePermissions` replaces a role's permissions with exactly the list you pass:

```php
app(SyncRolePermissions::class)->handle($role, [
    TenantPermissions::USER_VIEW_ANY,
    'invitation.create',
], actor: $admin);
```

- Permissions can be enum cases or names. Duplicates are ignored.
- A name that doesn't exist for the role's guard throws `Exceptions\InvalidRolePermissions`, and nothing is written. The same exception is thrown for the locked roles (`super_admin`, `owner`).
- The write runs in one transaction. The permission cache is cleared afterwards, then `Events\Admin\RolePermissionsChanged` is dispatched. If nothing changed, there is no event and no audit row.
- `RecordRolePermissionsChange` writes a `role_permission_changes` row: `role_id`, `role_name`, `guard_name`, `added`, `removed`, the nullable `actor` morph and `created_at`. The actor is `null` for console runs.

### The Role permissions page

**Security > Role permissions** in the admin panel (`ManageRolePermissions`, swap it with `usingRolePermissionsPage()`). It edits which role holds which permission, through `ApplyPermissionMatrix` and `SyncRolePermissions`.

- **Access:** admins with `permissions.manage`. Super admins always pass. Everyone else gets a 403 and no navigation item.
- **Layout:** one lazily loaded tab per guard (Admin team, Tenant users). Each tab has a collapsible section per resource, a row per permission and a column per role. There's a select all / none toggle per role and section. The `super_admin` and `owner` columns are locked.
- **Saving:** Save changes opens a confirmation that lists what each role gains and loses, and warns that the change applies to every tenant immediately. Reset discards unsaved edits. Every save is audited with the signed-in admin as actor.
- **Unknown permissions:** database rows with no enum case are shown read-only and left untouched.
- **Missing permissions:** enum cases with no database row are listed in a notice. Run the sync command to create them, because the page never creates rows.
- **Extending:** subclass the page and override the protected static `panels()`, `matrixLoader()` and `matrixSchema()`.

### Policies

`PolicyResolver` resolves the policy for a model per panel:

1. `App\Policies\{Admin|Tenant}\{Model}Policy`, if that class exists.
2. Otherwise `Slakbal\TenancyFoundation\Policies\{Admin|Tenant}\{Model}Policy`.

So to override a package policy, declare the app class (usually extending the package one). The base class is `Policies\AuthorisationPolicy`.

| Panel | Package policies |
|---|---|
| Admin | `AdminPolicy`, `ContactRequestPolicy`, `TenantPolicy`, `UserPolicy` |
| Tenant | `InvitationPolicy`, `TenantPolicy`, `UserPolicy` |

Rules in the tenant `UserPolicy`:

- Nobody but an owner can update, delete, remove or re-role an owner.
- You can't remove or re-role yourself.
- View-only members have no write abilities.
- Any non-owner may `leave` as themselves.
- `forceDelete` is always `false`.

## Registration

The tenant panel exposes `/register` (change it with Filament's `registrationRouteSlug()`). It is a two-step wizard: tenant name and subdomain, then the owner account (name attributes, email, password).

- `RegisterTenantOwner` creates the user and calls `CreateTenant`, which creates the tenant, the owner membership (default tenant if it's the user's first) and the owner role, all in one transaction. `TenantRegistered` is dispatched after commit.
- Filament sends the verification email. Once the email is verified, `RegistrationResponse` redirects to the tenant domain. The base `User` implements `MustVerifyEmail`.
- `RegisterTenant` reuses `CreateTenant`. It is only available to a signed-in user who has no tenant (`canView()`).
- **Override the forms:** extend `RegisterUser` / `RegisterTenant` and override `getTenantFormComponents()` and/or `getUserFormComponents()`. Extra attributes must be fillable. The width is `$maxWidth`. Register your pages with `usingRegisterUserPage()` / `usingRegisterTenantPage()`.
- **Reserved subdomains:** `reserved_subdomains`.
- **Business logic:** bind your own subclass of `RegisterTenantOwner` or `CreateTenant` in the container.

Registration always creates a tenant together with its owner. Tenant users never register themselves, and there is no open signup flag. Everyone else joins an existing tenant through an invitation, the tenant panel "create user" form, or the admin panel. If a product needs open signup, add it in the application.

## Invitations

Invitations are on by default and gated by `features.invitations`.

- **Tenant panel resource** (`InvitationResource`): list, an "Invite user" modal (email plus an assignable role), resend and revoke. Viewing the list needs `invitation.view_any` and `invitation.create`.
- **Admin panel:** an "Invite user" header action on a tenant's Users tab (needs `user.create`). It creates the invitation with no inviter, and the list shows "Support team" as the inviter.
- **`CreateInvitation`** refuses the owner role, current members and emails whose account is soft deleted (restore the account instead). It also asks `Tenant::canBeInvited($email, $role)` (hook, default `true`). It dispatches `InvitationIssued`, which sends `notifications.invitation_created`.
- **`ResendInvitation`** gives an unaccepted or expired invitation a new token and expiry, then dispatches `InvitationIssued` again.
- **Accepting:** the accept page is at `/invitations/accept/{invitation}` (signed route `filament.app.invitations.accept`, from `AcceptInvitation::routeName()`).
  - A new person sets a name and password and is signed in.
  - An existing account must sign in as that account first. A guest is redirected to login and then back.
  - `Actions\Invitation\AcceptInvitation` re-checks the invitation under a row lock and throws `Exceptions\InvalidInvitation` when it fails. It dispatches `InvitationAccepted` after commit.
- **Cleanup:** deleting a role cancels its pending invitations (`RoleObserver` → `ReleaseRoleInvitations`). Cancelling a tenant deletes its pending invitations. Deleting a tenant deletes all of its invitations. Expired invitations are pruned.

## Tenant members

- **Create user** (tenant panel, `user.create`): `CreateTenantUser` creates the user with an unusable random password and an unverified email, attaches them as an active member, assigns roles and dispatches `TenantUserCreated` inside the transaction.
  - The package sends nothing for this flow. Users who should sign in go through invitations instead.
  - Add product-specific behaviour in the `CreatedTenantUser` stub listener.
  - Override `IsTenantUser::canSignIn()` for user types that must never sign in. Such users can still request a password reset, so blocking them is the app's job.
- **Edit** (`UpdateTenantUser`): name and tenant roles. Email is read-only. Users in other tenants keep their name.
- **Activate / deactivate** (`ChangeTenantMemberStatus`, `user.update`): sets `tenant_user.status`. An inactive member can't open that tenant but keeps their other tenants. You can't change your own status or an owner's.
- **Owners:** `AddTenantOwner` / `RemoveTenantOwner`. The last owner can't be removed.
- **Remove / delete / leave** (`RemoveTenantMember`):
  - A user who belongs to other tenants is detached and loses this tenant's roles.
  - A user whose only tenant this is gets their roles dropped and is soft deleted. The membership stays, so you can restore them.
  - Owners are never removed.
  - `TenantMemberRemoved($tenant, $user, $actor, $accountDeleted)` is dispatched after commit. `notifications.member_removed` is mailed unless the user left on their own.
- **Restore** (`RestoreTenantMember`): restores a soft-deleted user as an active member and gives them a role if they have none.
- **Admin panel:** the tenant Users tab and the user Tenants tab offer manage roles (`SyncTenantRoles`), make or remove owner, remove from tenant, send password reset, impersonate and invite.

## Cancelling and deleting a tenant

The package owns the generic lifecycle. Your app owns the cancel screen and the cleanup of its own data.

| Piece | Where |
|---|---|
| `cancelled_at` and `scheduled_for_deletion_at`, `isCancelled()`, `isDueForDeletion()`, `active()` and `dueForDeletion()` scopes | package (`IsTenant`, migration) |
| `CancelTenant`: stamps `cancelled_at`, schedules deletion for the end of the day `cancellation.grace_days` later, dispatches `TenantCancelled` after commit | package |
| `AccountCancelled` mail to every owner (`notifications.cancelled`) | package |
| Login blocking: a user whose tenants are all cancelled can't sign in. Cancelled tenants leave the switcher and the default tenant. Requests to a cancelled tenant redirect to another active tenant (without host verification) or get the `CancelledTenantResponse` (see below) | package (`IsTenantUser`, `EnsureTenantIsNotCancelled`, `EnsureUserHasTenant`) |
| `ReactivateTenant`: clears both timestamps, dispatches `TenantReactivated` after commit | package |
| `DeleteTenant` and the daily `DeleteDueTenants` job | package |
| Admin **Tenants** resource with cancel and reactivate actions (`tenant.view_any`, `tenant.view`, `tenant.update`, `tenant.cancel`, `tenant.reactivate`) | package |
| Cancel screen, password confirmation, cleanup of **your own** models | application |

`DeleteTenant` only accepts a tenant that is due for deletion. Inside one transaction it:

1. dispatches `TenantDeleting`,
2. removes the members (`DeleteTenantMembers`: memberships, plus users who belong to no other tenant, which are force deleted),
3. removes the tenant's role and permission rows (`DeleteTenantPermissionData`, which never touches admin rows),
4. deletes the tenant.

Listeners run while the tenant and its members still exist. If one throws, nothing is deleted, and `DeleteDueTenants` retries the tenant on the next run (each tenant is wrapped in `rescue()`).

Reactivating does **not** restore the invitations that cancelling deleted. Owners send fresh invitations.

### Cancelled tenant hosts

`EnsureKnownTenantHost` (first in the tenant panel middleware) answers every tenant panel route on a cancelled tenant's host, login included, with `Http\Responses\Contracts\CancelledTenantResponse`: always in `domain` mode, in `subdomain` mode with `verify_subdomain_hosts`. The default, `Http\Responses\CancelledTenantResponse`, returns 404, so the host looks exactly like an unknown one. In `domain` mode the session on that host is invalidated first, so members are not signed in again when the tenant is reactivated. Unknown hosts are always a plain 404 (there is no tenant to pass).

Bind your own implementation in a service provider's `register()` to answer differently, for example a SaaS "come back" page:

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Slakbal\TenancyFoundation\Http\Responses\Contracts\CancelledTenantResponse;
use Symfony\Component\HttpFoundation\Response;

$this->app->bind(CancelledTenantResponse::class, fn () => new class implements CancelledTenantResponse
{
    public function toResponse(Request $request, Model $tenant): Response
    {
        return redirect()->away('https://example.com/come-back');
    }
});
```

The response is public: anyone can trigger it for a cancelled host. Never reactivate, or reveal owner details, from a URL alone. Whatever page reactivates must authenticate the owner itself (signed link, sign in on a central host, or the payment provider's verified webhook) and then call `ReactivateTenant`.

### Subscriptions and billing

The package has no subscription logic, and `cancelled_at` means "closed, waiting for deletion", not "unpaid". Keep the two apart:

| | Subscription lapsed (application) | Cancelled (package) |
|---|---|---|
| Tenant | Active, `cancelled_at` empty | `cancelled_at` set, deleted after `cancellation.grace_days` |
| Owner | Signs in; only the billing page works until they subscribe again | Cannot sign in on the tenant host |
| Tenant host | Normal | `CancelledTenantResponse` |

For the lapsed state use Filament's tenant billing on the tenant panel: `->tenantBillingProvider($provider)->requiresTenantSubscription()` (for example with Laravel Cashier). Filament adds the provider's subscribed middleware to each page and resource route, not to the billing route, so the owner reaches billing and subscribes again without a reactivation step. Who may use the billing page and what other members see meanwhile is up to the provider and the app. Package pages follow the panel setting; override `isTenantSubscriptionRequired()` on a page (for example the contact page) that must stay reachable.

Call `CancelTenant` only when the account is given up (for example after a long lapse, or when the owner closes it). `ReactivateTenant` still undoes it during the grace period.

## Events and extension points

### Events

| Event | Dispatched by | Package listener |
|---|---|---|
| `Tenant\TenantRegistered` (tenant, owner) | `CreateTenant`, after commit | `SendWelcomeNotification` |
| `Tenant\TenantCancelled` (tenant) | `CancelTenant`, after commit | `CancelledTenant` (runs `RevokePendingInvitations`), `SendCancellationNotification` |
| `Tenant\TenantReactivated` (tenant) | `ReactivateTenant`, after commit | `SendReactivationNotification` |
| `Tenant\TenantDeleting` (tenant) | `DeleteTenant`, inside its transaction, before anything is removed | `DeletedTenant` (runs `DeleteTenantInvitations`) |
| `Tenant\TenantUserCreated` (tenant, user, actor, data) | `CreateTenantUser`, inside its transaction | none |
| `Tenant\TenantMemberRemoved` (tenant, user, actor, accountDeleted) | `RemoveTenantMember`, after commit | `SendMemberRemovedNotification` |
| `Invitation\InvitationIssued` (invitation) | `CreateInvitation`, `ResendInvitation` | `SendInvitationNotification` (only with invitations on) |
| `Invitation\InvitationAccepted` (invitation, user) | `AcceptInvitation`, after commit | none |
| `Admin\AdminUserCreated` (admin) | `CreateAdminUser`, after commit | `SendAdminInvitation` |
| `Admin\RolePermissionsChanged` (roleId, roleName, guardName, added, removed, actor) | `SyncRolePermissions` | `RecordRolePermissionsChange` |
| `Contact\ContactRequestReceived` (contactRequest) | `CreateContactRequest` | `NotifyAdminsOfContactRequest` (queued, after commit) |
| `Contact\ContactRequestAnswered` (contactRequest) | `RespondToContactRequest` | `SendContactResponseNotification` |

All event classes live under `Slakbal\TenancyFoundation\Events\…`. The package also listens to Laravel's `PasswordReset` (`ActivateAdminOnPasswordReset`) and `Login` (`UpdateLastLogin`).

The package registers its listeners with `Event::listen`, because Laravel doesn't scan packages. Laravel discovers your own listeners in `app/Listeners`.

### App listeners with `actions()` lists

Each side that owns data has **one general listener per event**, with a protected `actions()` list. Each concern is its own action class, called with `handle(Model $tenant)`. To add behaviour, add a class and list it in `actions()`. Don't add a second listener, a config list or a closure.

```bash
php artisan vendor:publish --tag=tenancy-foundation-stubs
```

| Stub (`app/Listeners/Tenant`) | Event | Each action receives |
|---|---|---|
| `RegisteredTenant` | `TenantRegistered` | tenant |
| `CancelledTenant` | `TenantCancelled` | tenant |
| `ReactivatedTenant` | `TenantReactivated` | tenant |
| `DeletedTenant` | `TenantDeleting` | tenant (inside the deletion transaction) |
| `CreatedTenantUser` | `TenantUserCreated` | tenant, user, submitted form data |

```php
// app/Listeners/Tenant/DeletedTenant.php
protected function actions(): array
{
    return [
        DeleteSubscriptionData::class,
        DeleteTenantAddresses::class,
    ];
}
```

### Hooks

| Hook | Where | Use |
|---|---|---|
| `canBeInvited(string $email, Model $role): bool` | `IsTenant` (your `Tenant`) | Refuse invitations, for example by email domain |
| `canSignIn(): bool` | `HasTenantAccess` (your `User`) | Keep user types out of the panel |
| `CancelledTenantResponse` | container binding (`Http\Responses\Contracts\CancelledTenantResponse`) | What a cancelled tenant's host answers (default 404). See [Cancelled tenant hosts](#cancelled-tenant-hosts) |
| `getCurrentTenantLabel()` | `IsTenant` | Label under the tenant in the switcher (role names by default) |
| `currentTenantLabelStyle()` | `TenantPlugin` | Colour of that label |
| `getTenantFormComponents()`, `getUserFormComponents()` | `RegisterUser`, `RegisterTenant` | Registration fields |
| `fields()` | `PublicContactForm` | Public contact form fields (swap via `contact_requests.public_form`) |
| `redirectUrlAfterSubmit()` | `PublicContactForm` | Where to go after a submission (default `null`: stay and reset the form); the success notification shows on the next page |
| `permissionEnums()`, `viewOnlyPermissions()` | `TenantRolePermissionsSeeder` | App permissions, view-only defaults |
| `panels()`, `matrixLoader()`, `matrixSchema()` | `ManageRolePermissions` | Role permissions page |
| `mainComponents()`, `sideComponents()`, `detailsFields()`, `statusEntries()` | user forms and infolists (both panels), `TenantForm`, `TenantInfolist` | Add fields and sections |
| `rowActions()`, `bulkActions()`, `filters()`, `columns()` | shared `Components\Resources\Users\UsersTable` | Table customisation |
| Action classes | `src/Actions/*` | Bind a subclass in the container |
| Plugin `using*()` methods | `AdminPlugin`, `TenantPlugin` | Swap resources and pages |
| `notifications.*` | config | Swap or disable a notification |
| Translations | `lang/en.json` (JSON sentence keys) | Override in the app's `lang/en.json` |

## Contact requests

- **Public page:** `GET /{contact_requests.public_path}` on `APP_DOMAIN` (route `public.contact`, view `tenancy-foundation::contact`, Livewire `public-contact-form`). It has a Turnstile captcha when `l3aro/filament-turnstile` is installed (no captcha otherwise) and stores requests with source `public`.
- **Tenant page:** "Contact us" (`ContactUs`, slug `contact`, swap with `usingContactPage()`). It stores requests with source `tenant`.
- **Admin:** the `ContactRequestResource` lists requests and has a stats widget. Admins can respond (`RespondToContactRequest` mails `notifications.contact_response`) and change status. Permissions are `contact_request.*`.
- New requests notify admins who can view contact requests, plus super admins, through the bell (`notify_admins`, `notifications.contact_received`).
- Completed and dismissed requests are pruned after `prune_after_days`.

## Admin users

- `AdminUserResource` (slug `admins`, `admin_user.*` permissions) lists, views, creates and edits admins. It has role, status and trashed filters, plus delete, restore and force delete (force delete is super admin only).
- Creating an admin (`CreateAdminUser`) stores a random password with status `pending`, dispatches `AdminUserCreated` and mails `AdminInvited` with a set password link. Using the reset link activates the admin and marks the email as verified (`ActivateAdminOnPasswordReset`).
- With `features.password_auth` off, `AdminInvited` links to the admin login page instead, and the create and resend texts say so. The pending admin record is the invitation: it has no expiry, and deactivating or deleting the admin revokes it. The package does not activate the admin then; do it in your sign in flow (for example the SSO callback sets `status = active` on the first sign in of a pending admin).
- `tenancy-foundation:create-admin` creates the first admin from the command line (see [Commands](#commands)).
- `AdminStatus`: `pending`, `active`, `inactive`. Pending and active admins pass `canAccessPanel()`.
- Row and header actions: activate, deactivate, change role (`ChangeAdminUserRole`) and send password reset link. `AdminAccountGuard` protects your own account and the last active super admin.

## Impersonation

`Components\Tables\Actions\ImpersonateUserAction` (built on `stechstudio/filament-impersonate`) is on the admin users table, the user view and edit pages, and a tenant's Users tab.

- It needs `impersonate.user` (`Admin::canImpersonate()`).
- It signs in on the `web` guard and redirects to the user's default tenant dashboard on that tenant's host. If the user has no tenant, it stops with a notification.
- "Back" returns to the referring admin page.
- `subdomain` mode: it signs in within the shared session cookie (`SESSION_DOMAIN`); "Leave" in the banner restores the admin in that session.
- `domain` mode: the tenant host never sees the backoffice session (host-only cookies), so the admin stays signed in on the admin host and is redirected to a one-time handoff link on the tenant's host:
  - `Actions\Impersonation\IssueImpersonationHandoff` checks `canImpersonate()` (and `canBeImpersonated()` if the user model has it), stores the admin, user, tenant and return URL in the cache under a random 64-character token and returns a link on the tenant host whose path is signed (`signed:relative`; the controller enforces the host) (`/impersonation/handoff/{token}`, route `filament.app.impersonation.handoff`, registered by `TenantPlugin` in `domain` mode only). The link expires after 60 seconds (`IssueImpersonationHandoff::TTL_SECONDS`).
  - `Http\Controllers\RedeemImpersonationHandoff` accepts it once (cache lock plus pull), only on the tenant's own host, only while the admin can still access the admin panel and the user can still access the tenant. It replaces the tenant host session (new session id, earlier data dropped), signs the user in on the `web` guard without a `Login` event (so no last login update) and stores the impersonation the way `filament-impersonate` does, so its banner shows. Reused, expired, tampered or foreign-host links get 403 (404 on hosts that are not a tenant).
  - "Leave" (`filament-impersonate/leave`) runs `Http\Middleware\LeaveImpersonationOnTenantHost` first (added to `filament-impersonate.leave_middleware` in `domain` mode): it drops the tenant host session and returns to the admin page the action started from. That URL is accepted only on the admin host (http or https); anything else falls back to the admin users list.
  - Needs a cache store with atomic locks shared by all app servers (`redis`, `database`, `memcached`, `dynamodb`; `file` and `array` only on a single server). `EnterImpersonation` and `LeaveImpersonation` fire as in `subdomain` mode.

## Developer sign in

`Filament\Pages\Concerns\HasDeveloperSignIn` adds a local-only shortcut to a login page: an account picker and a "Developer sign in" button that sign in without a password or identity provider (useful with SSO, where there is no password form). The picker lists admins on the admin panel and the members of the host's tenant on the tenant panel (`models.admin`, `models.user`, `TenantResolution`), and the sign in still requires `canAccessPanel()`.

- Opt in per project with `features.dev_sign_in` (env `TENANCY_DEV_SIGN_IN`, default `false`). It also only works when `APP_ENV` is `local` and never in production (the production check is a second guard, in case the flag is left on by mistake): with another environment the components are not rendered and `signInAsDevAccount()` answers 404, whatever the setting.
- The package ships no login page for it. Add the trait to your own login page and spread the components into its schema:

```php
use Filament\Auth\Pages\Login as BaseLogin;
use Slakbal\TenancyFoundation\Filament\Pages\Concerns\HasDeveloperSignIn;

class Login extends BaseLogin
{
    use HasDeveloperSignIn;

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            // ... your sign in button or form
            ...$this->devSignInComponents(),
        ]);
    }
}
```

- It signs in through the panel guard and regenerates the session. It does not touch `sso_id`.
- Override `devAccountQuery()` to change which accounts are listed.

## Scheduled jobs

These are registered through the scheduler (`callAfterResolving(Schedule::class)`), each with `onOneServer()->withoutOverlapping()`:

| Schedule | Job | Switch |
|---|---|---|
| daily at `cancellation.deletion_time` | `Jobs\DeleteDueTenants` (queued) | `cancellation.schedule_deletion` |
| daily | `model:prune` (the app's prunable models) | `schedule_pruning` |
| daily | `model:prune --model=…` (`ContactRequest`, and `Invitation` when invitations are on) | `schedule_pruning` |

## Commands

| Command | Does |
|---|---|
| `tenancy-foundation:install` | Publishes the config (asks before overwriting an existing one), warns if spatie teams are off, prints the setup steps. Interactive with teams on: offers to migrate, seed `AdminRolePermissionsSeeder` and the `seeders.tenant_role_permissions` seeder, and run `create-admin` (each defaults to no). Without interaction it never overwrites and lists those steps instead |
| `tenancy-foundation:create-admin {email} {--role=}` | Interactive. Asks for the email (when not given) and each `user_name_attributes` field, validating as you type, then offers the admin guard roles from the database as a choice (default `super_admin`; app roles included). Creates a `pending` admin through `CreateAdminUser` (no actor, so it may create a super admin) and mails `AdminInvited`. Without interaction the role defaults to `super_admin`. Fails when no admin roles are seeded, on an email of an existing or deleted admin, or on an unknown `--role` (lists the available ones). Meant for the first super admin on a new install |
| `tenancy-foundation:sync-permissions` | Creates the missing permission rows for both guards: admin from `AdminPermissions`, tenant from the configured `seeders.tenant_role_permissions`. Reports orphaned rows (no enum case) and, when run interactively, asks per guard whether to delete them (default no; deleting also removes them from every role). Clears the permission cache. Idempotent. Never assigns permissions to roles |

## Testing

### The package

```bash
composer install
composer test        # both tenant resolutions, in parallel
```

Tests run on Orchestra Testbench with SQLite in memory, `tenancy-foundation.domain = tenant.test`, spatie teams on, and two test panels (`tests/Support`) that register the plugins. `stubs/testing` holds the users and permission migrations for Testbench.

Both tenant resolutions run the same tests:

| Run | Config | What runs |
|---|---|---|
| `vendor/bin/pest --parallel` | `phpunit.xml` | `tests/Feature` in `subdomain` mode, plus `tests/DomainMode` (domain-only behaviour, `DomainModeTestCase`) |
| `vendor/bin/pest --parallel --configuration=phpunit.domain.xml` | `phpunit.domain.xml` (forces `TENANCY_RESOLUTION=domain`) | `tests/Feature` again in `domain` mode, without the `subdomain` group |

Write feature tests mode-agnostic: create tenants with `tenantAddress('acme')` (`tests/Pest.php`: `['subdomain' => 'acme']` or `['domain' => 'acme.example.test']`), read the column with `TenantResolution::current()->attribute()` and build URLs with `urlFor()`. Tag a test (or a file, `pest()->group('subdomain')`) with the `subdomain` group only when it checks subdomain-only behaviour; put domain-only behaviour in `tests/DomainMode`.

### An application built on it

The package's `tests/` folder isn't autoloaded in apps. The app template keeps its own `Tests\Concerns\InteractsWithTenancy`. The essentials:

```php
Filament::setCurrentPanel(Filament::getPanel(FilamentPanel::TENANT->getPanelId()));

$tenant = Tenant::factory()->create();
$user = User::factory()->create();
$tenant->members()->attach($user, ['is_owner' => false, 'is_default_tenant' => true]);

setPermissionsTeamId($tenant->getKey());
$user->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

$this->actingAs($user, AuthGuard::TENANT->value);
Filament::setTenant($tenant);
```

- Admins: call `setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID)` before assigning roles, and use `actingAs($admin, AuthGuard::ADMIN->value)`.
- Permission rows don't exist until you seed or call `Permission::findOrCreate($name, $guard)`.
- Tenant URLs are `http://{subdomain}.{tenancy-foundation.domain}/…`.
- Use `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array`, or `Notification::fake()` / `Event::fake()`, to assert notifications and events.

## Troubleshooting

| Symptom | Cause |
|---|---|
| Tenant URL is `https://tenant1./` | `APP_DOMAIN` isn't set |
| Login succeeds but the tenant asks you to log in again | `SESSION_DOMAIN` isn't `.your-domain`, or old cookies are present (change `SESSION_COOKIE`) |
| Verification, welcome or invitation emails never arrive | The queue worker isn't running |
| `/contact` on the main domain is 404 | `APP_DOMAIN` is empty or `contact_requests.public_path` is `null` |
| New permissions are missing on the Role permissions page | Run `tenancy-foundation:sync-permissions` |
| `Target class ...Seeders\... does not exist` | Run `composer update slakbal/tenancy-foundation` to refresh the autoload map |
| Spatie team errors on roles | `permission.teams` and `team_foreign_key = tenant_id` weren't set before migrating |
| Tenants are never deleted | The scheduler or queue worker isn't running, or `cancellation.schedule_deletion` is `false` |

## Upgrading

Read [CHANGELOG.md](CHANGELOG.md) before you update. Entries marked **Breaking** need action, such as moved namespaces, fresh migrations or config keys to remove. After an upgrade:

```bash
composer update slakbal/tenancy-foundation
php artisan migrate
php artisan tenancy-foundation:sync-permissions
```

Then grant any new permissions on the Role permissions page.

## Tenant access policy

Decide whether an email address may sign in to a tenant, for products with SSO or any other login that does not use invitations. The package only provides the data and the decision; the sign-in flow calls it.

- `tenants.access_policy` (`AccessPolicy` enum, default `open_except_blocked`): `open_except_blocked` lets every domain in except those with a `block` rule; `allow_list_only` lets in only domains with an `allow` rule. Allow rules are ignored under the first policy and block rules under the second.
- `domain_rules` (`models.domain_rule`, `Tenant::domainRules()`): `tenant_id`, `domain` (stored lower case, unique per tenant, exact match) and `type` (`DomainRuleType`: `allow` | `block`).
- `Actions\Auth\EvaluateEmailAccess::handle($tenant, $email)` returns an `EmailAccess` case (`ALLOWED_BY_RULE`, `ALLOWED_BY_POLICY`, `BLOCKED_BY_RULE`, `BLOCKED_BY_POLICY`; `isAllowed()`). Call it on every sign in, not only the first, so a changed rule applies at the next login.

Existing projects are unchanged: with the default policy and no rules every email is allowed. Needs the new `tenants.access_policy` column and `domain_rules` table (`migrate:fresh` in a project that already ran the old create migrations).
