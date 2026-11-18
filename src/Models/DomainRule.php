<?php

namespace Slakbal\TenancyFoundation\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Database\Factories\DomainRuleFactory;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;
use Slakbal\TenancyFoundation\Models\Concerns\HasUlidKey;
use Slakbal\TenancyFoundation\Models\Concerns\Tenancy\BelongsToTenant;

#[Fillable(['tenant_id', 'value', 'type'])]
class DomainRule extends Model
{
    /** @use HasFactory<DomainRuleFactory> */
    use BelongsToTenant, HasFactory, HasUlidKey;

    protected static function newFactory(): DomainRuleFactory
    {
        return DomainRuleFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => DomainRuleType::class,
        ];
    }

    protected function value(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }
}
