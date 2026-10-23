<?php

namespace Slakbal\TenancyFoundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\TenantRegistered;
use Spatie\Permission\PermissionRegistrar;

readonly class CreateTenant
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, Model $owner): Model
    {
        return DB::transaction(function () use ($data, $owner): Model {
            $isFirstTenant = ! $owner->tenants()->exists();

            $tenant = config('tenancy-foundation.models.tenant')::create($data);

            $tenant->members()->attach($owner->getKey(), [
                'is_owner' => true,
                'is_default_tenant' => $isFirstTenant,
            ]);

            $this->assignOwnerRole($owner, $tenant);

            DB::afterCommit(fn () => TenantRegistered::dispatch($tenant, $owner));

            return $tenant;
        });
    }

    protected function assignOwnerRole(Model $owner, Model $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->getRoleClass()::findOrCreate(TenantRoles::OWNER->value, AuthGuard::TENANT->value);

        $registrar->setPermissionsTeamId($tenant->getKey());
        $owner->unsetRelation('roles')->assignRole(TenantRoles::OWNER);
        $registrar->setPermissionsTeamId($previousTeamId);
        $owner->unsetRelation('roles');
    }
}
