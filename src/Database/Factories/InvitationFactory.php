<?php

namespace Slakbal\TenancyFoundation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

class InvitationFactory extends Factory
{
    public function modelName(): string
    {
        return config('tenancy-foundation.models.invitation');
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => config('tenancy-foundation.models.tenant')::factory(),
            'invited_by' => config('tenancy-foundation.models.user')::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role_id' => config('tenancy-foundation.models.role')::findOrCreate(TenantRoles::VIEW_ONLY->value, AuthGuard::TENANT->value)->getKey(),
            'token' => Str::random(64),
            'expires_at' => now()->addDays((int) config('tenancy-foundation.invitations.expiry_days')),
            'accepted_at' => null,
        ];
    }
}
