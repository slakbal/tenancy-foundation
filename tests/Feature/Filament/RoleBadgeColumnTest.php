<?php

use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Columns\RoleBadgeColumn;

test('the known roles use the label, colour and icon of the enum', function (TenantRoles $role) {
    $column = RoleBadgeColumn::make();

    expect($column->formatState($role->value))->toBe($role->getLabel())
        ->and($column->getColor($role->value))->toBe($role->getColor())
        ->and($column->getIcon($role->value))->toBe($role->getIcon());
})->with(TenantRoles::cases());

test('a custom role gets a readable label, a grey badge and no icon', function () {
    $column = RoleBadgeColumn::make();

    expect($column->formatState('billing_manager'))->toBe('Billing Manager')
        ->and($column->getColor('billing_manager'))->toBe('gray')
        ->and($column->getIcon('billing_manager'))->toBeNull();
});
