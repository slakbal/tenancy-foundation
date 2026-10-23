<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Admin;

use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;

trait CanImpersonate
{
    public function canImpersonate()
    {
        return $this->can(AdminPermissions::IMPERSONATE_USER);
    }
}
