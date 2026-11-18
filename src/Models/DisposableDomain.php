<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['domain'])]
class DisposableDomain extends Model
{
    public $timestamps = false;

    protected function domain(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }
}
