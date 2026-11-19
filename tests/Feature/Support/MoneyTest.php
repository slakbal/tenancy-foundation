<?php

use Slakbal\TenancyFoundation\Filament\Components\Forms\MoneyInput;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\MoneyColumn;
use Slakbal\TenancyFoundation\Support\Money;

beforeEach(function () {
    config(['filament-defaults.formats.currency' => 'eur', 'filament-defaults.formats.number_locale' => 'de']);
});

test('the currency comes from the format defaults and can be overridden', function () {
    expect(Money::currency())->toBe('EUR')
        ->and(Money::currency('usd'))->toBe('USD');

    config(['filament-defaults.formats.currency' => null]);

    expect(Money::currency())->toBe('USD');
});

test('the number of decimals follows the currency', function () {
    expect(Money::digits('EUR'))->toBe(2)
        ->and(Money::digits('JPY'))->toBe(0);
});

test('amounts convert to minor units and back', function () {
    expect(Money::toMinor('149.90'))->toBe(14990)
        ->and(Money::toMinor(0.1 + 0.2))->toBe(30)
        ->and(Money::toMinor('1500', 'JPY'))->toBe(1500)
        ->and(Money::toAmount(14990))->toBe(149.9)
        ->and(Money::toAmount(null))->toBe(0.0);
});

test('money is formatted in the number locale', function () {
    expect(Money::format(14990))->toBe("149,90\u{A0}€")
        ->and(Money::symbol())->toBe('€');
});

test('the money input shows the currency symbol and is numeric', function () {
    $input = MoneyInput::make('price_cents');

    expect($input->getPrefixLabel())->toBe('€')
        ->and($input->isNumeric())->toBeTrue()
        ->and($input->getMinValue())->toBe(0)
        ->and(MoneyInput::make('price_cents', 'USD')->getPrefixLabel())->toBe('$');
});

test('the money column formats minor units and can show a Free badge for zero', function () {
    $plain = MoneyColumn::make('price_cents');
    $free = MoneyColumn::make('price_cents', freeBadge: true);

    expect($plain->isSortable())->toBeTrue()
        ->and($free->isBadge())->toBeTrue()
        ->and($plain->isBadge())->toBeFalse();
});
