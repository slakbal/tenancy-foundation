<?php

use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Slakbal\TenancyFoundation\Notifications\WelcomeToTenant;

return [

    'domain' => env('APP_DOMAIN'),

    'models' => [
        'tenant' => Tenant::class,
        'user' => User::class,
        'admin' => Admin::class,
        'role' => Role::class,
    ],

    'user_name_attributes' => ['name'],

    'reserved_subdomains' => ['www', 'admin', 'app', 'api', 'mail', 'dev', 'test'],

    'admin_permissions_team_id' => 1,

    'notifications' => [
        'welcome' => WelcomeToTenant::class,
    ],

    'redirects' => [
        'logout_route' => null,
    ],

    'register_auth_defaults' => true,

    'register_last_login_listener' => true,
];
