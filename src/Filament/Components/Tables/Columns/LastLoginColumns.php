<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

class LastLoginColumns
{
    public static function make(): array
    {
        return [
            TextColumn::make('last_login_ip')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('last_login_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
