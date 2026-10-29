<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class UserNameSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return collect(config('tenancy-foundation.user_name_attributes'))
            ->values()
            ->map(fn (string $attribute, int $index): Component => TextInput::make($attribute)
                ->label(__(Str::of($attribute)->replace('_', ' ')->ucfirst()->toString()))
                ->required()
                ->maxLength(100)
                ->autofocus($index === 0))
            ->all();
    }
}
