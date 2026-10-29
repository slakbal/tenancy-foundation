<?php

namespace Slakbal\TenancyFoundation\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

readonly class UpdateAdminUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $admin, array $data, Model $actor): Model
    {
        $role = Arr::pull($data, 'role');

        return DB::transaction(function () use ($admin, $data, $role, $actor): Model {
            $attributes = [...config('tenancy-foundation.user_name_attributes'), 'email'];

            $admin->forceFill(Arr::only($data, $attributes))->save();

            if ($role !== null) {
                app(ChangeAdminUserRole::class)->handle($admin, $role, $actor);
            }

            return $admin;
        });
    }
}
