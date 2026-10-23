<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantUser extends Pivot
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.user'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.tenant'));
    }
}
