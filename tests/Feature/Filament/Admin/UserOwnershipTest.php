<?php

use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\TransferTenantOwnership;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
});

test('the administrator transfer refuses a user who already owns the tenant or is not a member', function (string $candidate) {
    $user = $candidate === 'an owner' ? $this->owner : User::factory()->create();

    expect(fn () => app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, $user))
        ->toThrow(InvalidArgumentException::class);
})->with(['an owner', 'a non member']);

test('the administrator transfer demotes every previous owner', function () {
    $coOwner = User::factory()->create();
    $this->tenant->members()->attach($coOwner->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);

    app(TransferTenantOwnership::class)->handleAsAdministrator($this->tenant, $this->member);

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()]);
});
