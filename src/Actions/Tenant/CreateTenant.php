<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\TenantRegistered;

readonly class CreateTenant
{
    public function __construct(private AssignTenantRole $assignTenantRole) {}

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

            $this->assignTenantRole->handle($owner, $tenant, TenantRoles::OWNER);

            DB::afterCommit(fn () => TenantRegistered::dispatch($tenant, $owner));

            return $tenant;
        });
    }
}
