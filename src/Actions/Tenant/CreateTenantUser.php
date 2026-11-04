<?php

namespace Slakbal\TenancyFoundation\Actions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Slakbal\TenancyFoundation\Actions\Roles\SyncTenantRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Events\Tenant\TenantUserCreated;

readonly class CreateTenantUser
{
    public function __construct(private SyncTenantRoles $syncTenantRoles) {}

    public static function emailTakenMessage(): string
    {
        return config('tenancy-foundation.features.invitations')
            ? __('This email already has an account. Invite them to this tenant from the Invitations page instead.')
            : __('This email already has an account.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $tenant, array $data, ?Model $actor = null): Model
    {
        $email = mb_strtolower($data['email']);
        $roles = array_map(TenantRoles::from(...), Arr::get($data, 'tenant_roles', []));
        $userModel = config('tenancy-foundation.models.user');

        throw_if(in_array(TenantRoles::OWNER, $roles, true), new AuthorizationException(__('The owner role cannot be given when creating a user.')));
        throw_if($userModel::withTrashed()->where('email', $email)->exists(), ValidationException::withMessages(['email' => static::emailTakenMessage()]));

        return DB::transaction(function () use ($tenant, $data, $actor, $email, $roles, $userModel): Model {
            $user = $userModel::query()->create([
                ...Arr::only($data, config('tenancy-foundation.user_name_attributes')),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
            ]);

            $tenant->members()->syncWithoutDetaching([$user->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);

            $this->syncTenantRoles->handle($user, $tenant, $roles);

            TenantUserCreated::dispatch($tenant, $user, $actor, $data);

            return $user;
        });
    }
}
