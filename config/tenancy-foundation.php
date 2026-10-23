<?php

use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\Role;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

return [

    'domain' => env('APP_DOMAIN'),

    'models' => [
        'tenant' => Tenant::class,
        'user' => User::class,
        'admin' => Admin::class,
        'role' => Role::class,
    ],

    'user_name_attributes' => ['name'],

    'admin_permissions_team_id' => 1,

    'redirects' => [
        'logout_route' => null,
        'tenant_registration_path' => 'new',
    ],

    'register_auth_defaults' => true,

    'register_last_login_listener' => true,
];
