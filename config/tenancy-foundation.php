<?php

use Slakbal\TenancyFoundation\Livewire\PublicContactForm;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;
use Slakbal\TenancyFoundation\Notifications\AccountReactivated;
use Slakbal\TenancyFoundation\Notifications\AdminInvited;
use Slakbal\TenancyFoundation\Notifications\ContactRequestResponded;
use Slakbal\TenancyFoundation\Notifications\NewContactRequest;
use Slakbal\TenancyFoundation\Notifications\WelcomeToTenant;

return [

    /*
    |--------------------------------------------------------------------------
    | Application domain
    |--------------------------------------------------------------------------
    |
    | The root domain the application is served on, without scheme or port
    | (e.g. "myapp.test"). Every tenant is served on a subdomain of it
    | ({subdomain}.myapp.test); the registration form shows it as the
    | subdomain suffix and the tenant URLs in emails and redirects use it.
    |
    */

    'domain' => env('APP_DOMAIN'),

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
    |
    */

    'models' => [
        'tenant' => Tenant::class,
        'user' => User::class,
        'admin' => Admin::class,
        'role' => Role::class,
        'contact_request' => ContactRequest::class,
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
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Subdomains a tenant may not register, because they are used by the
    | application itself or would be confusing. Enforced by the validation
    | of the subdomain field on the tenant registration form.
    |
    */

    'reserved_subdomains' => ['www', 'admin', 'app', 'api', 'mail', 'dev', 'test'],

    /*
    |--------------------------------------------------------------------------
    | Admin permissions team id
    |--------------------------------------------------------------------------
    |
    | Spatie permissions run with teams enabled and the team is the tenant.
    | Admin roles and permissions are not tenant specific, so they are stored
    | under this one fixed team id. It is applied when the admin panel boots
    | and whenever the package checks admin permissions outside that panel
    | (for example when notifying admins of a new contact request).
    |
    */

    'admin_permissions_team_id' => 1,

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
    | admin_invited     Emailed to a new admin with a link to set their password.
    | contact_response  Emailed to the requestor when an admin responds to
    |                   their contact request.
    | contact_received  Sent to the admin panel bell (database notification)
    |                   when a new contact request comes in.
    |
    */

    'notifications' => [
        'welcome' => WelcomeToTenant::class,
        'cancelled' => AccountCancelled::class,
        'reactivated' => AccountReactivated::class,
        'admin_invited' => AdminInvited::class,
        'contact_response' => ContactRequestResponded::class,
        'contact_received' => NewContactRequest::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant cancellation
    |--------------------------------------------------------------------------
    |
    | grace_days         Days between a tenant being cancelled and its data being
    |                    deleted. The tenant can be reactivated until then.
    | schedule_deletion  Register the scheduled job that deletes tenants whose
    |                    grace period has passed. Disable it to schedule
    |                    DeleteDueTenants yourself.
    | deletion_time      Time of day (server time, HH:MM) the job runs.
    |
    */

    'cancellation' => [
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
    | public_form       Livewire component behind the public page. Extend
    |                   PublicContactForm and override fields() to change it.
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
    |               not exist.
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
