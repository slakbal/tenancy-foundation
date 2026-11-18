<?php

use Slakbal\TenancyFoundation\Database\Seeders\AdminRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Database\Seeders\TenantRolePermissionsSeeder;
use Slakbal\TenancyFoundation\Livewire\PublicContactForm;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Models\DomainRule;
use Slakbal\TenancyFoundation\Models\Invitation;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\RolePermissionChange;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\Admin\AdminInvited;
use Slakbal\TenancyFoundation\Notifications\Contact\ContactRequestResponded;
use Slakbal\TenancyFoundation\Notifications\Contact\NewContactRequest;
use Slakbal\TenancyFoundation\Notifications\Invitation\InvitationCreated;
use Slakbal\TenancyFoundation\Notifications\Tenant\AccountCancelled;
use Slakbal\TenancyFoundation\Notifications\Tenant\AccountReactivated;
use Slakbal\TenancyFoundation\Notifications\Tenant\MemberRemoved;
use Slakbal\TenancyFoundation\Notifications\Tenant\WelcomeToTenant;

return [

    /*
    |--------------------------------------------------------------------------
    | Application domain
    |--------------------------------------------------------------------------
    |
    | The root domain the application is served on, without scheme or port
    | (e.g. "myapp.test"). With resolution "subdomain" every tenant is served
    | on a subdomain of it ({subdomain}.myapp.test); the tenant forms show it
    | as the subdomain suffix and the tenant URLs in emails and redirects use
    | it. With resolution "domain" it is only the central host of the public
    | contact page and may be left empty; a tenant may not use it as its own
    | domain.
    |
    */

    'domain' => env('APP_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Tenant resolution
    |--------------------------------------------------------------------------
    |
    | How the tenant panel finds the tenant from the request host.
    |
    | subdomain  (default) The tenant is {tenants.subdomain}.{domain}. Login,
    |            registration and verification run on the central domain, so
    |            the session cookie must be shared across subdomains: set
    |            SESSION_DOMAIN to the domain with a leading dot.
    | domain     The tenant is the full host stored in tenants.domain (e.g.
    |            "academy.example.com"), so tenants can live on unrelated
    |            domains. Every tenant signs in on its own host. Side effects:
    |            the session cookie is forced host-only (session.domain = null,
    |            SESSION_DOMAIN is ignored) and BindSessionToHost resets a
    |            session cookie replayed on another tenant host to a guest
    |            session, so tenants never share a session;
    |            the tenant host routes never match `domain` (it stays central);
    |            the admin forms edit "domain" instead of "subdomain"; each
    |            tenant host must point at the app (DNS and TLS per host).
    |
    | Env: TENANCY_RESOLUTION. Both columns exist on the tenants table (the
    | unused one stays null), so switching needs no migration, only data.
    |
    */

    'resolution' => env('TENANCY_RESOLUTION', 'subdomain'),

    /*
    |--------------------------------------------------------------------------
    | Verify subdomain hosts
    |--------------------------------------------------------------------------
    |
    | Subdomain mode only (domain mode always verifies). When true, the tenant
    | panel (login, password reset, invitations included) answers on a
    | subdomain of `domain` only when it belongs to an existing tenant:
    | unknown and reserved subdomains and hosts outside `domain` return 404.
    | The central host (`domain` itself) is never checked, so login and
    | registration keep working there. A cancelled tenant's subdomain gets the
    | CancelledTenantResponse (default 404, bind your own to redirect); the
    | shared session is left alone, because it also serves the user's other
    | tenants. Needs `domain` (APP_DOMAIN); without it nothing is checked.
    |
    | Default false: unchanged, every subdomain shows the tenant login.
    | Env: TENANCY_VERIFY_SUBDOMAIN_HOSTS.
    |
    */

    'verify_subdomain_hosts' => (bool) env('TENANCY_VERIFY_SUBDOMAIN_HOSTS', false),

    /*
    |--------------------------------------------------------------------------
    | Admin panel (backoffice) host
    |--------------------------------------------------------------------------
    |
    | Where the admin panel is served. AdminPlugin applies both to the panel.
    |
    | domain  Host of the admin panel, without scheme or port (e.g.
    |         "backoffice.example.com"). Default null: with resolution
    |         "subdomain" the panel answers on every host (unchanged); with
    |         resolution "domain" it falls back to `domain` above, so the
    |         backoffice never answers on a tenant host. In domain mode one
    |         of the two is required: with both empty the app refuses to boot
    |         (InvalidTenancyConfiguration). Side effects: the panel's routes only match this host
    |         (other hosts return 404), tenants may not use it as their
    |         domain, and a subdomain of `domain` is reserved for tenants.
    |         The host must point at the app (DNS and TLS). In domain mode
    |         the session cookie is host-only, so staff sign in on this host
    |         only. Env: ADMIN_DOMAIN.
    | path    URL path prefix of the admin panel. Default "admin"; may be
    |         empty ("") or "/" when the panel has its own domain (both mean no
    |         prefix; slashes are trimmed). Env: ADMIN_PATH.
    | hide_super_admins  Default false: every admin sees the super admins. true
    |         hides admins who hold the super_admin role from every admin who is
    |         not a super admin (a global scope on the Admin model while an admin
    |         is signed in: lists, searches, counts, pickers, relations). Super
    |         admins still see each other, and sign in, commands, jobs and guests
    |         are not filtered. Create such a super admin with
    |         tenancy-foundation:create-admin; the role is only assignable by a
    |         super admin. Env: ADMIN_HIDE_SUPER_ADMINS.
    |
    */

    'admin' => [
        'domain' => env('ADMIN_DOMAIN'),
        'path' => env('ADMIN_PATH', 'admin'),
        'hide_super_admins' => env('ADMIN_HIDE_SUPER_ADMINS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The model classes the package uses. Point an entry at your own class
    | (usually one that extends the package model) to swap it everywhere:
    | actions, resources, factories and the auth guards all resolve the
    | class from here.
    |
    | tenant           The tenant (account) model.
    | user             Tenant panel users; also the model of the "users" provider.
    | admin            Admin panel users; also the model of the "admins" provider.
    | role             The Spatie role model.
    | contact_request  Public and tenant contact form submissions.
    | invitation       Invitations to join a tenant (only with features.invitations).
    | domain_rule      Per tenant allow/block rules for an email domain or a full email address (see the tenant access_policy).
    |
    */

    'models' => [
        'tenant' => Tenant::class,
        'user' => User::class,
        'admin' => Admin::class,
        'role' => Role::class,
        'contact_request' => ContactRequest::class,
        'invitation' => Invitation::class,
        'domain_rule' => DomainRule::class,
        'role_permission_change' => RolePermissionChange::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Seeders
    |--------------------------------------------------------------------------
    |
    | admin_role_permissions   The seeder that defines the admin (backoffice)
    |                          permissions. Default: the package
    |                          AdminRolePermissionsSeeder. It must be (a
    |                          subclass of) that seeder. Point it at your
    |                          subclass that overrides permissionEnums() to add
    |                          app permission enums: the Role permissions page
    |                          and tenancy-foundation:sync-permissions then
    |                          treat them as normal, editable permissions, and
    |                          a first seed grants them to the admin role.
    |
    | tenant_role_permissions  The seeder that defines the tenant permissions.
    |                          Default: the package TenantRolePermissionsSeeder.
    |                          It must be (a subclass of) that seeder. Point it
    |                          at your subclass that overrides permissionEnums()
    |                          to add app permission enums, as above.
    |
    | Any other class throws InvalidPermissionSeeder. tenancy-foundation:install
    | seeds the configured classes.
    |
    */

    'seeders' => [
        'admin_role_permissions' => AdminRolePermissionsSeeder::class,
        'tenant_role_permissions' => TenantRolePermissionsSeeder::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | User name attributes
    |--------------------------------------------------------------------------
    |
    | The user table column(s) holding a person's name. Use ["name"] for a
    | single column or ["first_name", "last_name"] for two. This drives the
    | registration form fields, the name column in the users tables, the
    | sole-owner messages and the user and admin factories.
    |
    */

    'user_name_attributes' => ['name'],

    /*
    |--------------------------------------------------------------------------
    | Email change verification
    |--------------------------------------------------------------------------
    |
    | When true, a user or admin who changes their email on the profile page
    | keeps the old address until they click a link sent to the new one (valid
    | for an hour); the old address gets a notice with a link to block the
    | change. Applies to both panels. Set to false to save changes directly.
    |
    */

    'email_change_verification' => true,

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | invitations         Invite people into a tenant by email: the tenant panel
    |                     invitations resource and accept page, the invitation
    |                     permissions and the pruning of old invitations. Set to
    |                     false for a product without invitations. The migrations
    |                     always run, whatever the flags say.
    | multi_tenant_users  (default true) Users may belong to, or own, more than one
    |                     tenant. false hides the "Owner of" and "Member of"
    |                     columns of the user tables, which would only repeat the
    |                     one tenant. Memberships and the data model are unchanged.
    | password_auth       (default true) Password sign in on the tenant panel.
    |                     false removes the password reset routes (tenant and
    |                     admin panels), hides every "Send password reset link"
    |                     action (tables and view/edit pages) and turns off user
    |                     registration. The admin invitation (admin_invited) then
    |                     links to the admin login page instead of a set password
    |                     page; the app activates the pending admin on first
    |                     sign in (for example in its SSO callback). The login
    |                     page itself is the app's to replace, not this flag's.
    | registration        (default true) The user registration page of the tenant
    |                     panel. Only available together with password_auth.
    | email_verification  (default true) Email verification prompt and routes of
    |                     the tenant panel. false never asks users to verify;
    |                     the User model may still implement MustVerifyEmail. It also
    |                     hides the "Verified" column and filter of the user and
    |                     admin tables, the "Email verified" infolist entries and the
    |                     "Mark email verified" action; the columns stay in the
    |                     database.
    | tenant_registration (default null) The "register a tenant" page of the
    |                     tenant panel. null follows the resolution: on with
    |                     "subdomain", off with "domain" (tenants are then created
    |                     by staff). true or false always forces it; set it with
    |                     TENANCY_TENANT_REGISTRATION.
    | csp                 (default false) A nonce based Content-Security-Policy
    |                     on the admin and tenant panels, through
    |                     asignua/filament-csp-nonce (installed with this package;
    |                     the plugin is registered by AdminPlugin and TenantPlugin).
    |                     Tune it in the application's config/csp-nonce.php
    |                     (publish with `php artisan vendor:publish
    |                     --tag=csp-nonce-config`): report_only (env
    |                     CSP_REPORT_ONLY, the dependency default is false =
    |                     enforcing; start with true), directives for extra
    |                     script, style, font, image or frame sources, and the
    |                     violation report endpoint (POST /csp/report). Public
    |                     pages outside the panels add the `csp.nonce` middleware
    |                     themselves. Run `php artisan view:clear` once after
    |                     turning it on. Env: TENANCY_CSP.
    | security_headers    (default false) Adds X-Content-Type-Options,
    |                     X-Frame-Options (SAMEORIGIN), Referrer-Policy
    |                     (strict-origin-when-cross-origin) and Permissions-Policy
    |                     (camera, microphone, geolocation and payment off) to
    |                     every response, and Strict-Transport-Security (one
    |                     year) in production over HTTPS. Registered as global
    |                     middleware. Env: TENANCY_SECURITY_HEADERS.
    | dev_sign_in         (default false) Lets a login page that uses the
    |                     HasDeveloperSignIn trait show an account picker and a
    |                     "Developer sign in" button (admins on the admin panel,
    |                     members of the host's tenant on the tenant panel) that
    |                     signs in without a password or identity provider. It
    |                     only works when the application environment is "local"
    |                     (APP_ENV) and is never available in production, whatever
    |                     this setting is (checked twice, in case the flag is
    |                     left on by mistake); the action answers 404 elsewhere.
    |                     The package ships no login page for it:
    |                     the application adds the trait to its own login page
    |                     and spreads devSignInComponents() into the page schema.
    |                     It logs in through the panel guard and does not touch
    |                     sso_id. Env: TENANCY_DEV_SIGN_IN.
    | disposable_emails   (default false) Refuse disposable (throwaway) email
    |                     addresses. true: EvaluateEmailAccess answers
    |                     blocked_disposable for an address whose domain is on
    |                     the list (after the tenant's address and domain rules,
    |                     so an allow rule still lets it in; before the access
    |                     policy), and the NotDisposableEmail rule refuses it on
    |                     the package forms (registration, profile email,
    |                     invitations, tenant user and admin forms,
    |                     tenancy-foundation:create-admin). The list lives in the
    |                     central disposable_domains table (create_domain_rules_table
    |                     migration); fill it by running
    |                     tenancy-foundation:refresh-disposable-domains once after
    |                     turning this on. The package then schedules that command
    |                     weekly (needs the scheduler). While the table is empty
    |                     every address passes and a warning is logged. See
    |                     disposable_emails.source below.
    |
    */

    'features' => [
        'invitations' => true,
        'multi_tenant_users' => true,
        'password_auth' => true,
        'registration' => true,
        'email_verification' => true,
        'tenant_registration' => env('TENANCY_TENANT_REGISTRATION', true),
        'csp' => env('TENANCY_CSP', false),
        'security_headers' => env('TENANCY_SECURITY_HEADERS', false),
        'dev_sign_in' => env('TENANCY_DEV_SIGN_IN', false),
        'disposable_emails' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Disposable emails
    |--------------------------------------------------------------------------
    |
    | Only used with features.disposable_emails.
    |
    | source  URL of a JSON array of disposable email domains (strings), read by
    |         tenancy-foundation:refresh-disposable-domains. Default: the
    |         domains.json of github.com/disposable/disposable-email-domains on
    |         jsDelivr. The domains are stored lower case and matched exactly
    |         (no subdomains, no MX lookup). A download that fails, is not a JSON
    |         array of strings or is empty is refused and the stored list stays.
    |
    */

    'disposable_emails' => [
        'source' => 'https://cdn.jsdelivr.net/gh/disposable/disposable-email-domains@master/domains.json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Response headers
    |--------------------------------------------------------------------------
    |
    | Header name => value pairs the application adds to every response when
    | features.security_headers is on, for example
    | ['Cross-Origin-Opener-Policy' => 'same-origin']. They are merged over
    | the package defaults, so a name that matches a default (for example
    | 'X-Frame-Options') replaces it, and null or an empty string removes it.
    | Default: [] (the package defaults only). No effect with the feature off.
    |
    */

    'response_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | expiry_days     Days an invitation link stays valid.
    | retention_days  Accepted invitations are pruned this many days after they
    |                 were accepted; unaccepted ones when they expire.
    |
    */

    'invitations' => [
        'expiry_days' => env('INVITATION_EXPIRY_DAYS', 7),
        'retention_days' => env('INVITATION_RETENTION_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains a tenant may not register, because they are used by the
    | application itself or would be confusing. Enforced by the validation
    | of the subdomain field on the tenant forms (resolution "subdomain").
    |
    */

    'reserved_subdomains' => ['www', 'admin', 'app', 'api', 'mail', 'dev', 'test'],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | The notification class sent for each event. Replace a class to change
    | the content, or set it to null to stop that notification being sent.
    | The notifications are queued, so a queue worker has to be running.
    |
    | welcome           Emailed to the new owner after a tenant is registered.
    | cancelled         Emailed to the tenant owners when the tenant is cancelled.
    | reactivated       Emailed to the tenant owners when a cancelled tenant is
    |                   reactivated.
    | admin_invited     Emailed to a new admin with a link to set their password
    |                   (with features.password_auth off: a link to the admin
    |                   login page).
    | contact_response  Emailed to the requestor when an admin responds to
    |                   their contact request.
    | contact_received  Sent to the admin panel bell (database notification)
    |                   when a new contact request comes in.
    | member_removed    Emailed to a user an admin removed from the tenant (not
    |                   when they left on their own). The mail goes out even
    |                   when the account was deleted with the removal.
    | invitation_created  Emailed to the invited person with a signed accept link
    |                   (only with features.invitations).
    |
    */

    'notifications' => [
        'welcome' => WelcomeToTenant::class,
        'cancelled' => AccountCancelled::class,
        'reactivated' => AccountReactivated::class,
        'admin_invited' => AdminInvited::class,
        'contact_response' => ContactRequestResponded::class,
        'contact_received' => NewContactRequest::class,
        'member_removed' => MemberRemoved::class,
        'invitation_created' => InvitationCreated::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant cancellation
    |--------------------------------------------------------------------------
    |
    | enabled            Whether tenants can be cancelled. Default true. false hides
    |                    the cancel and reactivate actions (tenant list, view and
    |                    edit pages; the policy denies both abilities), and the
    |                    status, cancelled and deletion scheduled columns, entries
    |                    and filters in the admin tenant resource. Existing
    |                    cancelled tenants stay cancelled and the lifecycle code,
    |                    middleware and daily job still run; no migration or env
    |                    var is needed. Env: TENANT_CANCELLATION_ENABLED.
    | grace_days         Days between a tenant being cancelled and its data being
    |                    deleted. The tenant can be reactivated until then.
    | schedule_deletion  Register the scheduled job that deletes tenants whose
    |                    grace period has passed. Disable it to schedule
    |                    DeleteDueTenants yourself.
    | deletion_time      Time of day (server time, HH:MM) the job runs.
    |
    */

    'cancellation' => [
        'enabled' => env('TENANT_CANCELLATION_ENABLED', true),
        'grace_days' => env('TENANT_CANCELLATION_GRACE_DAYS', 30),
        'schedule_deletion' => true,
        'deletion_time' => '01:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact requests
    |--------------------------------------------------------------------------
    |
    | prune_after_days  Completed and dismissed contact requests are deleted
    |                   this many days after they were closed (see
    |                   schedule_pruning below). New requests are never
    |                   pruned. The admin stats widget also reports on this
    |                   window. Use 0 or null to keep requests forever; the
    |                   stats then cover all time.
    | notify_admins     Notify admins who may view contact requests (and
    |                   super admins) when a new request is received. See
    |                   notifications.contact_received.
    | public_path       Path of the public contact page on the main domain
    |                   (route name public.contact). null disables the page and
    |                   the route. It is only registered when `domain` is set.
    |                   The form has a Turnstile captcha only when the app
    |                   requires l3aro/filament-turnstile (TURNSTILE_* env).
    | public_form       Livewire component behind the public page. Extend
    |                   PublicContactForm and override fields() to change it,
    |                   or redirectUrlAfterSubmit() to leave the page after a
    |                   submission (default null: stay and reset the form).
    |
    */

    'contact_requests' => [
        'prune_after_days' => env('CONTACT_REQUEST_PRUNE_AFTER_DAYS', 90),
        'notify_admins' => true,
        'public_path' => 'contact',
        'public_form' => PublicContactForm::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | logout_route  Name of the route users are sent to after logging out, and
    |               after registering on a panel without tenancy. Falls back to
    |               the application root ("/") when null or when the route does
    |               not exist. With resolution "domain" logging out ignores it
    |               and returns to the panel's login page on the same host,
    |               because each host is its own site.
    |
    */

    'redirects' => [
        'logout_route' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Schedule pruning
    |--------------------------------------------------------------------------
    |
    | Register the daily model:prune commands: one for the models in your
    | app and one for the package models that use pruning (such as contact
    | requests). Both run on one server only and never overlap. Disable it
    | if you schedule pruning yourself.
    |
    */

    'schedule_pruning' => true,

    /*
    |--------------------------------------------------------------------------
    | Register auth defaults
    |--------------------------------------------------------------------------
    |
    | Register the "admin" guard, the "admins" user provider and the "admins"
    | password broker at runtime, using models.admin, unless your own
    | config/auth.php already defines them. Disable it to configure the
    | admin authentication entirely yourself.
    |
    */

    'register_auth_defaults' => true,

    /*
    |--------------------------------------------------------------------------
    | Register last login listener
    |--------------------------------------------------------------------------
    |
    | Listen for the Login event and store the time and IP address on the
    | user (last_login_at and last_login_ip). Disable it if you track logins
    | differently.
    |
    */

    'register_last_login_listener' => true,
];
