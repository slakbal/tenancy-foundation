<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Enums\AccessPolicy;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\DomainRuleType;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\EditTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages\ViewTenant;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\RelationManagers\DomainRulesRelationManager;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\DomainRule;
use Slakbal\TenancyFoundation\Models\Tenant;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $this->tenant = Tenant::factory()->create();
});

function domainRulesManager(Tenant $tenant, string $pageClass = EditTenant::class): mixed
{
    return Livewire::test(DomainRulesRelationManager::class, ['ownerRecord' => $tenant, 'pageClass' => $pageClass]);
}

test('an admin who may update the tenant adds address and domain rules, normalised and unique per tenant', function () {
    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE), AuthGuard::ADMIN->value);
    DomainRule::factory()->create(['value' => 'gmail.com']);

    domainRulesManager($this->tenant)
        ->callAction(TestAction::make('create')->table(), ['value' => ' Gmail.COM ', 'type' => DomainRuleType::BLOCK->value])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('create')->table(), ['value' => 'Lead.Dev@gmail.com', 'type' => DomainRuleType::ALLOW->value])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make('create')->table(), ['value' => 'GMAIL.com ', 'type' => DomainRuleType::ALLOW->value])
        ->assertHasActionErrors(['value']);

    // A failed create keeps its modal mounted, so the next invalid value gets a fresh component.
    domainRulesManager($this->tenant)
        ->callAction(TestAction::make('create')->table(), ['value' => '@gmail', 'type' => DomainRuleType::ALLOW->value])
        ->assertHasActionErrors(['value' => 'regex']);

    expect($this->tenant->domainRules()->orderBy('value')->pluck('type', 'value')->all())
        ->toBe(['gmail.com' => DomainRuleType::BLOCK, 'lead.dev@gmail.com' => DomainRuleType::ALLOW]);
});

test('an admin who may only view the tenant sees the rules but cannot change them', function () {
    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW), AuthGuard::ADMIN->value);
    $rule = DomainRule::factory()->create(['tenant_id' => $this->tenant->getKey()]);

    expect(DomainRulesRelationManager::canViewForRecord($this->tenant, ViewTenant::class))->toBeTrue();

    domainRulesManager($this->tenant)
        ->assertCanSeeTableRecords([$rule])
        ->assertActionHidden(TestAction::make('create')->table())
        ->assertActionHidden(TestAction::make('delete')->table($rule));
});

test('the access policy is saved from the tenant edit form', function () {
    $this->actingAs(adminWithAccess(AdminPermissions::TENANT_VIEW_ANY, AdminPermissions::TENANT_VIEW, AdminPermissions::TENANT_UPDATE), AuthGuard::ADMIN->value);

    Livewire::test(EditTenant::class, ['record' => $this->tenant->getRouteKey()])
        ->fillForm(['access_policy' => AccessPolicy::ALLOW_LIST_ONLY->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->tenant->refresh()->access_policy)->toBe(AccessPolicy::ALLOW_LIST_ONLY);
});
