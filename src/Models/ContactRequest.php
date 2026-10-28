<?php

namespace Slakbal\TenancyFoundation\Models;

use Slakbal\TenancyFoundation\Enums\ContactSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'message', 'source'])]
class ContactRequest extends Model
{
    protected function casts(): array
    {
        return [
            'source' => ContactSource::class,
        ];
    }
}
