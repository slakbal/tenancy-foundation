<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Slakbal\TenancyFoundation\Enums\MemberStatus;

class TenantUser extends Pivot
{
    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.user'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(config('tenancy-foundation.models.tenant'));
    }
}
