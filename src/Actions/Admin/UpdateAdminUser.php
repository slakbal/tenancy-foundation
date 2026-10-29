<?php

namespace Slakbal\TenancyFoundation\Actions\Admin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

readonly class UpdateAdminUser
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Model $admin, array $data): Model
    {
        $attributes = [...config('tenancy-foundation.user_name_attributes'), 'email'];

        $admin->forceFill(Arr::only($data, $attributes))->save();

        return $admin;
    }
}
