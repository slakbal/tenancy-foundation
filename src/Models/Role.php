<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function invitations(): HasMany
    {
        return $this->hasMany(config('tenancy-foundation.models.invitation'));
    }
}
