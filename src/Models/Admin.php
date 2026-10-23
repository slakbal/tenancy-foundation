<?php

namespace Slakbal\TenancyFoundation\Models;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Slakbal\TenancyFoundation\Database\Factories\AdminFactory;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Concerns\Admin\CanImpersonate;
use Slakbal\TenancyFoundation\Models\Concerns\Admin\IsAdminUser;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;
use Slakbal\TenancyFoundation\Models\Concerns\HasUserAttributes;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password'])]
class Admin extends Authenticatable implements FilamentUser
{
    use CanImpersonate, HasFactory, HasRoles, HasUlidKey, HasUserAttributes, IsAdminUser, Notifiable, SoftDeletes;

    protected string $guard_name = AuthGuard::ADMIN->value;

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): AdminFactory
    {
        return AdminFactory::new();
    }
}
