<?php

use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages\ListUsers;
use Slakbal\TenancyFoundation\Models\Tenant;
use Slakbal\TenancyFoundation\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->tenant = Tenant::factory()->create();
    $this->viewer = User::factory()->create();
    $this->tenant->members()->attach($this->viewer, ['is_owner' => false, 'is_default_tenant' => true]);

    setPermissionsTeamId($this->tenant->getKey());
});

test('a tenant user with USER_VIEW_ANY sees only users belonging to the current tenant', function () {
    Permission::findOrCreate(TenantPermissions::USER_VIEW_ANY->value, AuthGuard::TENANT->value);
    $this->viewer->givePermissionTo(TenantPermissions::USER_VIEW_ANY);

    $colleague = User::factory()->create();
    $this->tenant->members()->attach($colleague, ['is_owner' => false, 'is_default_tenant' => true]);

    $outsider = User::factory()->create();
    Tenant::factory()->create()->members()->attach($outsider, ['is_owner' => false, 'is_default_tenant' => true]);

    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->viewer, $colleague])
        ->assertCanNotSeeTableRecords([$outsider]);
});

test('a tenant user without USER_VIEW_ANY is forbidden from the users list', function () {
    $this->actingAs($this->viewer, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);

    Livewire::test(ListUsers::class)->assertForbidden();
});
