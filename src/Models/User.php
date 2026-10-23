<?php

namespace Slakbal\TenancyFoundation\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Slakbal\TenancyFoundation\Database\Factories\UserFactory;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;
use Slakbal\TenancyFoundation\Models\Concerns\HasUserAttributes;
use Slakbal\TenancyFoundation\Models\Concerns\Tenancy\IsTenantUser;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    use HasFactory, HasRoles, HasUlidKey, HasUserAttributes, IsTenantUser, Notifiable, SoftDeletes;

    protected string $guard_name = AuthGuard::TENANT->value;

    protected function casts(): array
    {
        return [
            'last_login_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
