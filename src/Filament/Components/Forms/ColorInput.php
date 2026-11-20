<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Forms;

use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Support\Icons\Heroicon;
use Slakbal\TenancyFoundation\Enums\BadgeColor;

class ColorInput
{
    public static function make(string $name = 'color', ?string $helperText = null): ColorPicker
    {
        return ColorPicker::make($name)
            ->label(__('Colour'))
            ->default(BadgeColor::DEFAULT->getHex())
            ->helperText($helperText)
            ->suffixAction(
                Action::make('resetColor')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->tooltip(__('Reset to the default colour'))
                    ->action(fn (ColorPicker $component): ColorPicker => $component->state(BadgeColor::DEFAULT->getHex())),
            );
    }
}
