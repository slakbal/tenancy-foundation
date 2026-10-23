<?php

namespace Slakbal\TenancyFoundation\Models\Concerns\Tenancy;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.tenant'));
    }
}
