<?php

namespace Slakbal\TenancyFoundation\Models;

use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Slakbal\TenancyFoundation\Database\Factories\AdminFactory;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Concerns\Admin\CanImpersonate;
use Slakbal\TenancyFoundation\Models\Concerns\Admin\IsAdminUser;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
class Admin extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<AdminFactory> */
    use CanImpersonate, HasFactory, HasRoles, HasUlidKey, IsAdminUser, Notifiable, SoftDeletes;

    public const int PERMISSIONS_TEAM_ID = 0;

    protected string $guard_name = AuthGuard::ADMIN->value;

    protected $attributes = [
        'status' => 'active',
    ];

    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => AdminStatus::class,
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
