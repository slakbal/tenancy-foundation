<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn;

class NameColumn
{
    public static function make(string $name): TextColumn
    {
        $attributes = config('tenancy-foundation.user_name_attributes');

        return TextColumn::make($name)
            ->sortable($attributes)
            ->searchable(query: function ($query, $search) use ($attributes) {
                foreach ($attributes as $attribute) {
                    $query->orWhere($attribute, 'like', "%{$search}%");
                }
            });
    }
}
