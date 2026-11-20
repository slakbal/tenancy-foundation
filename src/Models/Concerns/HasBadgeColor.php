<?php

namespace Slakbal\TenancyFoundation\Models\Concerns;

use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Slakbal\TenancyFoundation\Enums\BadgeColor;

trait HasBadgeColor
{
    public function badgeColor(): array|string
    {
        return filled($this->color) ? Color::hex($this->color) : BadgeColor::DEFAULT->value;
    }

    protected function color(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? Str::lower(trim($value)) : null);
    }
}
