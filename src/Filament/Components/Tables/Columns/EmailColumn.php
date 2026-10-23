<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

class EmailColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->label(__('Email'))
            ->searchable()
            ->sortable();
    }
}
