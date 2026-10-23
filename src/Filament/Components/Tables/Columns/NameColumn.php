<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

class NameColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->sortable(['first_name', 'last_name'])
            ->searchable(query: function ($query, $search) {
                $query->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
    }
}
