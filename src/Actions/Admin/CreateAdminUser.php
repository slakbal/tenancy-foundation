<?php

namespace Slakbal\TenancyFoundation\Actions\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\AdminStatus;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Events\AdminUserCreated;
use Slakbal\TenancyFoundation\Support\AdminAccountGuard;

readonly class CreateAdminUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Model $actor): Model
    {
        $role = $data['role'] ?? null;

        if ($role === AdminRoles::SUPER_ADMIN->value && ! AdminAccountGuard::isSuperAdmin($actor)) {
            throw new AuthorizationException(__('Only a super admin can assign the super admin role.'));
        }

        return DB::transaction(function () use ($data, $role): Model {
            $attributes = [...config('tenancy-foundation.user_name_attributes'), 'email'];

            $admin = new (config('tenancy-foundation.models.admin'));
            $admin->forceFill([
                ...Arr::only($data, $attributes),
                'password' => Str::random(64),
                'status' => AdminStatus::PENDING,
            ])->save();

            if ($role !== null) {
                $admin->syncRoles([$role]);
            }

            DB::afterCommit(fn () => AdminUserCreated::dispatch($admin));

            return $admin;
        });
    }
}
