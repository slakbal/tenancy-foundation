<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Models\Admin;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();

    setPermissionsTeamId(1);
});

test('each ability requires its permission', function (string $ability, AdminPermissions $permission, bool $onRecord) {
    $subject = $onRecord ? Admin::factory()->create() : Admin::class;

    $without = Admin::factory()->create();
    $with = adminWithAccess($permission);

    expect($without->can($ability, $subject))->toBeFalse();
    expect($with->can($ability, $subject))->toBeTrue();
})->with([
    'viewAny' => ['viewAny', AdminPermissions::ADMIN_USER_VIEW_ANY, false],
    'view' => ['view', AdminPermissions::ADMIN_USER_VIEW, true],
    'create' => ['create', AdminPermissions::ADMIN_USER_CREATE, false],
    'update' => ['update', AdminPermissions::ADMIN_USER_UPDATE, true],
    'delete' => ['delete', AdminPermissions::ADMIN_USER_DELETE, true],
    'restoreAny' => ['restoreAny', AdminPermissions::ADMIN_USER_RESTORE, false],
]);
