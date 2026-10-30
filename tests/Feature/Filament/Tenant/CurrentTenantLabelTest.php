<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Roles\AssignTenantRole;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    $this->other = app(CreateTenant::class)->handle(['name' => 'Globex', 'subdomain' => 'globex'], User::factory()->create());
});

test('the tenant switcher shows the role of the signed in user in the current tenant', function () {
    $member = User::factory()->create();
    $this->tenant->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => true]);
    $this->other->members()->attach($member, ['is_owner' => false, 'is_default_tenant' => false]);
    app(AssignTenantRole::class)->handle($member, $this->tenant, TenantRoles::ADMIN);
    app(AssignTenantRole::class)->handle($member, $this->other, TenantRoles::VIEW_ONLY);

    $this->actingAs($member, AuthGuard::TENANT->value);

    setPermissionsTeamId($this->tenant->getKey());
    Filament::setTenant($this->tenant, isQuiet: true);
    $member->unsetRelation('roles');

    expect($this->tenant->getCurrentTenantLabel())->toBe(TenantRoles::ADMIN->getLabel())
        ->and($this->other->getCurrentTenantLabel())->toBe(__('Active'));

    setPermissionsTeamId($this->other->getKey());
    Filament::setTenant($this->other, isQuiet: true);
    $member->unsetRelation('roles');

    expect($this->other->getCurrentTenantLabel())->toBe(TenantRoles::VIEW_ONLY->getLabel());
});

test('the label shows owner for an owner and falls back to active for a guest', function () {
    expect($this->tenant->getCurrentTenantLabel())->toBe(__('Active'));

    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    setPermissionsTeamId($this->tenant->getKey());
    Filament::setTenant($this->tenant, isQuiet: true);
    $this->owner->unsetRelation('roles');

    expect($this->tenant->getCurrentTenantLabel())->toBe(TenantRoles::OWNER->getLabel());
});

test('the tenant panel colours the current tenant label with the primary colour', function () {
    $html = Filament::renderHook(Filament\View\PanelsRenderHook::HEAD_END)->toHtml();

    expect($html)->toContain('.fi-tenant-menu-trigger-current-tenant-label{color:var(--primary-600)}')
        ->and($html)->toContain('.dark .fi-tenant-menu-trigger-current-tenant-label{color:var(--primary-400)}');
});
