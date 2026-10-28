<?php

use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\AccountCancelled;
use Slakbal\TenancyFoundation\Notifications\ContactRequestResponded;
use Slakbal\TenancyFoundation\Notifications\NewContactRequest;
use Slakbal\TenancyFoundation\Notifications\WelcomeToTenant;

return [

    'domain' => env('APP_DOMAIN'),

    'models' => [
        'tenant' => Tenant::class,
        'user' => User::class,
        'admin' => Admin::class,
        'role' => Role::class,
        'contact_request' => ContactRequest::class,
    ],

    'user_name_attributes' => ['name'],

    'reserved_subdomains' => ['www', 'admin', 'app', 'api', 'mail', 'dev', 'test'],

    'admin_permissions_team_id' => 1,

    'notifications' => [
        'welcome' => WelcomeToTenant::class,
        'cancelled' => AccountCancelled::class,
        'contact_response' => ContactRequestResponded::class,
        'contact_received' => NewContactRequest::class,
    ],

    'cancellation' => [
        'grace_days' => env('TENANT_CANCELLATION_GRACE_DAYS', 30),
        'schedule_deletion' => true,
        'deletion_time' => '01:00',
    ],

    'contact_requests' => [
        'prune_after_days' => env('CONTACT_REQUEST_PRUNE_AFTER_DAYS', 90),
        'schedule_pruning' => true,
        'notify_admins' => true,
    ],

    'redirects' => [
        'logout_route' => null,
    ],

    'register_auth_defaults' => true,

    'register_last_login_listener' => true,
];
