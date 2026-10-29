<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    setPermissionsTeamId($this->tenant->getKey());
});

test('each ability requires its permission', function (string $ability, TenantPermissions $permission, bool $onRecord) {
    $subject = $onRecord ? User::factory()->create() : User::class;

    $without = User::factory()->create();
    $with = tenantUserWithPermission($this->tenant, $permission);

    expect($without->can($ability, $subject))->toBeFalse();
    expect($with->can($ability, $subject))->toBeTrue();
})->with([
    'viewAny' => ['viewAny', TenantPermissions::USER_VIEW_ANY, false],
    'view' => ['view', TenantPermissions::USER_VIEW, true],
    'create' => ['create', TenantPermissions::USER_CREATE, false],
    'update' => ['update', TenantPermissions::USER_UPDATE, true],
    'delete' => ['delete', TenantPermissions::USER_DELETE, true],
]);
