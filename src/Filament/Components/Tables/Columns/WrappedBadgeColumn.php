<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

class WrappedBadgeColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->wrap()
            ->badge();
    }
}
