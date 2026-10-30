<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Livewire\Livewire;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantPermissions;
use Slakbal\TenancyFoundation\Filament\Components\Schemas\TenantDetailsSchema;
use Slakbal\TenancyFoundation\Filament\Pages\EditTenantProfile;
use Slakbal\TenancyFoundation\Models\User;
use Spatie\Permission\Models\Permission;

class ExtendedTenantProfile extends EditTenantProfile
{
    protected function getTenantProfileFormComponents(): array
    {
        return [...parent::getTenantProfileFormComponents(), TextInput::make('billing_email')];
    }
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::bootCurrentPanel();

    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);
    setPermissionsTeamId($this->tenant->getKey());

    $this->actingAs($this->owner, AuthGuard::TENANT->value);
    Filament::setTenant($this->tenant);
});

test('a subclass can extend the profile form through the components hook', function () {
    Livewire::test(ExtendedTenantProfile::class)
        ->assertFormFieldExists('name')
        ->assertFormFieldExists('billing_email');
});

test('the subdomain is shown read only with the same prefix and suffix as at registration', function () {
    Livewire::test(EditTenantProfile::class)
        ->assertFormFieldExists('subdomain', fn (TextInput $field): bool => $field->isReadOnly()
            && $field->getPrefixLabel() === 'https://'
            && $field->getSuffixLabel() === '.'.config('tenancy-foundation.domain'));
});

test('the name can be edited but the subdomain cannot, even when the request is tampered with', function () {
    Livewire::test(EditTenantProfile::class)
        ->assertFormFieldExists('name')
        ->fillForm(['name' => 'Acme Renamed', 'subdomain' => 'hacked'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->tenant->fresh()->subdomain)->toBe('acme')
        ->and($this->tenant->fresh()->name)->toBe('Acme Renamed');
});

test('the full tenant url can be copied to the clipboard', function () {
    $url = TenantDetailsSchema::url('acme');

    expect($url)->toBe(appDomainScheme().'acme.'.config('tenancy-foundation.domain'));

    $html = Livewire::test(EditTenantProfile::class)->html();

    expect($html)->toContain('navigator.clipboard.writeText')
        ->and(html_entity_decode($html))->toContain(str_replace('/', '\\/', $url));
});

test('only the owner of the tenant can open the profile page', function (bool $withEveryPermission) {
    expect(EditTenantProfile::canView($this->tenant))->toBeTrue();

    $member = User::factory()->create();
    $this->tenant->members()->syncWithoutDetaching([$member->getKey() => ['is_owner' => false, 'is_default_tenant' => true]]);

    if ($withEveryPermission) {
        foreach (TenantPermissions::cases() as $permission) {
            Permission::findOrCreate($permission->value, AuthGuard::TENANT->value);
            $member->givePermissionTo($permission);
        }
    }

    $this->actingAs($member->fresh(), AuthGuard::TENANT->value);

    expect(EditTenantProfile::canView($this->tenant))->toBeFalse();
    Livewire::test(EditTenantProfile::class)->assertNotFound();
})->with([
    'a member without permissions' => false,
    'a member holding every tenant permission' => true,
]);
