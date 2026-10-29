<?php

namespace Slakbal\TenancyFoundation\Actions\Admin;

use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Enums\AdminStatus;

readonly class ChangeAdminUserStatus
{
    public function handle(Model $admin, AdminStatus $status): Model
    {
        $attributes = ['status' => $status];

        if ($status === AdminStatus::INACTIVE) {
            $attributes['remember_token'] = null;
        }

        $admin->forceFill($attributes)->save();

        return $admin;
    }
}
