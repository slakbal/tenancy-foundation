<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    // Policy resolution relies on Gate::guessPolicyNamesUsing, which is
    // registered inside TenantPanelProvider's bootUsing callback. That
    // callback only runs when the panel actually boots (normally via the
    // SetUpPanel middleware on a real request), so force it here.
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    setPermissionsTeamId($this->tenant->getKey());
});

test('each ability requires its own permission, not TENANT_VIEW_ANY', function (string $ability, TenantPermissions $permission, bool $onRecord) {
    $subject = $onRecord ? $this->tenant : Tenant::class;

    $withViewAny = tenantMemberWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);
    $with = tenantMemberWithPermission($this->tenant, $permission);

    expect($withViewAny->can($ability, $subject))->toBeFalse();
    expect($with->can($ability, $subject))->toBeTrue();
})->with([
    'view' => ['view', TenantPermissions::TENANT_VIEW, true],
    'create' => ['create', TenantPermissions::TENANT_CREATE, false],
    'update' => ['update', TenantPermissions::TENANT_UPDATE, true],
    'delete' => ['delete', TenantPermissions::TENANT_DELETE, true],
]);

test('viewAny requires TENANT_VIEW_ANY', function () {
    $withoutPermission = User::factory()->create();
    $withPermission = tenantMemberWithPermission($this->tenant, TenantPermissions::TENANT_VIEW_ANY);

    expect($withoutPermission->can('viewAny', Tenant::class))->toBeFalse();
    expect($withPermission->can('viewAny', Tenant::class))->toBeTrue();
});
