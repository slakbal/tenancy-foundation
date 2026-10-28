<?php

use Filament\Panel;
use Filament\Support\Enums\Width;
use Slakbal\TenancyFoundation\FilamentDefaultsServiceProvider;

function applyPanelWidth(?string $width): Panel
{
    config(['filament-defaults.panel.max_content_width' => $width]);

    (new FilamentDefaultsServiceProvider(app()))->apply();

    return Panel::make()->id('width-test');
}

afterEach(fn () => applyPanelWidth('full'));

test('the panel content width follows the configured filament width value', function () {
    expect(applyPanelWidth('7xl')->getMaxContentWidth())->toBe(Width::SevenExtraLarge)
        ->and(applyPanelWidth('screen-2xl')->getMaxContentWidth())->toBe(Width::ScreenTwoExtraLarge)
        ->and(applyPanelWidth('full')->getMaxContentWidth())->toBe(Width::Full);
});

test('top navigation no longer forces the full content width', function () {
    config(['filament-defaults.panel.top_navigation' => true]);

    expect(applyPanelWidth('5xl')->getMaxContentWidth())->toBe(Width::FiveExtraLarge);
});
