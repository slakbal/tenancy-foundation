<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RolePermissionChange extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'added' => 'array',
            'removed' => 'array',
        ];
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
