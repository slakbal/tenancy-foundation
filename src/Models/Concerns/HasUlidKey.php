<?php

namespace Slakbal\TenancyFoundation\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

trait HasUlidKey
{
    use HasUlids;

    // Which column(s) should receive a unique identifier?
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid'; // or 'uuid'
    }
}
