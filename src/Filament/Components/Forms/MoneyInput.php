<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Forms;

use Filament\Forms\Components\TextInput;
use Slakbal\TenancyFoundation\Support\Money;

class MoneyInput
{
    public static function make(string $name, ?string $currency = null): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->minValue(0)
            ->step(1 / 10 ** Money::digits($currency))
            ->prefix(Money::symbol($currency))
            ->formatStateUsing(fn (?int $state): float => Money::toAmount($state, $currency))
            ->dehydrateStateUsing(fn (mixed $state): int => Money::toMinor($state, $currency));
    }
}
