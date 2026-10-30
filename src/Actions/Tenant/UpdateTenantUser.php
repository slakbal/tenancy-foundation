<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;

readonly class UpdateTenantUser
{
    public function __construct(private SyncTenantRoles $syncTenantRoles) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $user, Model $tenant, array $data, ?Model $actor = null): Model
    {
        $roles = Arr::get($data, 'tenant_roles');

        if ($roles !== null && ($user->isOwner($tenant) || $actor?->is($user))) {
            throw new AuthorizationException(__('The roles of an owner or of yourself cannot be changed here.'));
        }

        DB::transaction(function () use ($user, $tenant, $data, $roles): void {
            $user->forceFill(Arr::only($data, config('tenancy-foundation.user_name_attributes')))->save();

            if ($roles !== null) {
                $this->syncTenantRoles->handle($user, $tenant, array_map(TenantRoles::from(...), $roles));
            }
        });

        return $user;
    }
}
