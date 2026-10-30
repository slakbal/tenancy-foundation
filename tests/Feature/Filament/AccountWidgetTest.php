<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Widgets\AccountWidget;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

test('the tenant dashboard widget shows the welcome, the name, the role badge and a logout button', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $owner = User::factory()->create(['name' => 'Olivia Owner']);
    $tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $owner);
    setPermissionsTeamId($tenant->getKey());
    $this->actingAs($owner, AuthGuard::TENANT->value);
    Filament::setTenant($tenant, isQuiet: true);

    Livewire::test(AccountWidget::class)
        ->assertSee('Olivia Owner')
        ->assertSee(TenantRoles::OWNER->getLabel())
        ->assertSee(__('filament-panels::widgets/account-widget.actions.logout.label'));
});

test('the same widget shows the admin role on the admin dashboard', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Filament::bootCurrentPanel();
    setPermissionsTeamId(Admin::PERMISSIONS_TEAM_ID);

    $admin = adminWithAccess(AdminRoles::SUPPORT);
    $this->actingAs($admin, AuthGuard::ADMIN->value);

    Livewire::test(AccountWidget::class)
        ->assertSee($admin->name)
        ->assertSee(AdminRoles::SUPPORT->getLabel());
});

test('the widget is only available to signed in users', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    expect(AccountWidget::canView())->toBeFalse();
});
