<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\IconColumn;

class VerifiedColumn
{
    public static function make(string $name = 'email_verified_at'): IconColumn
    {
        return IconColumn::make($name)
            ->label(__('Verified'))
            ->boolean()
            ->sortable();
    }
}
