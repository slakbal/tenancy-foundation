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

test('the panel content width follows the configured filament width value', function (string $configured, Width $expected) {
    expect(applyPanelWidth($configured)->getMaxContentWidth())->toBe($expected);
})->with([
    '7xl' => ['7xl', Width::SevenExtraLarge],
    'screen-2xl' => ['screen-2xl', Width::ScreenTwoExtraLarge],
    'full' => ['full', Width::Full],
]);

test('top navigation no longer forces the full content width', function () {
    config(['filament-defaults.panel.top_navigation' => true]);

    expect(applyPanelWidth('5xl')->getMaxContentWidth())->toBe(Width::FiveExtraLarge);
});
