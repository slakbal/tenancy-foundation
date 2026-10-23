<?php

namespace Slakbal\TenancyFoundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

readonly class RegisterTenantOwner
{
    public function __construct(private CreateTenant $createTenant) {}

    /**
     * @param  array<string, mixed>  $tenantData
     * @param  array<string, mixed>  $userData
     */
    public function handle(array $tenantData, array $userData): Model
    {
        return DB::transaction(function () use ($tenantData, $userData): Model {
            $user = config('tenancy-foundation.models.user')::create($userData);

            $this->createTenant->handle($tenantData, $user);

            return $user;
        });
    }
}
