<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;
use Slakbal\TenancyFoundation\Support\Money;

class MoneyColumn
{
    public static function make(string $name, ?string $currency = null, bool $freeBadge = false): TextColumn
    {
        $column = TextColumn::make($name)
            ->formatStateUsing(fn (?int $state): string => $freeBadge && ($state ?? 0) === 0 ? __('Free') : Money::format($state, $currency))
            ->sortable();

        if ($freeBadge) {
            $column
                ->badge(fn (?int $state): bool => ($state ?? 0) === 0)
                ->color(fn (?int $state): ?string => ($state ?? 0) === 0 ? 'success' : null);
        }

        return $column;
    }
}
