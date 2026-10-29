<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    setPermissionsTeamId(1);
});

test('each ability requires its permission (tenant users, as seen by admins)', function (string $ability, AdminPermissions $permission, bool $onRecord) {
    $subject = $onRecord ? User::factory()->create() : User::class;

    $without = Admin::factory()->create();
    $with = adminWithPermission($permission);

    expect($without->can($ability, $subject))->toBeFalse();
    expect($with->can($ability, $subject))->toBeTrue();
})->with([
    'viewAny' => ['viewAny', AdminPermissions::USER_VIEW_ANY, false],
    'view' => ['view', AdminPermissions::USER_VIEW, true],
    'create' => ['create', AdminPermissions::USER_CREATE, false],
    'delete' => ['delete', AdminPermissions::USER_DELETE, true],
]);
