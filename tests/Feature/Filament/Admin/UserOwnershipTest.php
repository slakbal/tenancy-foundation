<?php

use Slakbal\TenancyFoundation\Actions\Tenant\AddTenantOwner;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Actions\Tenant\RemoveTenantOwner;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Acme', 'subdomain' => 'acme'], $this->owner);

    $this->member = User::factory()->create();
    $this->tenant->members()->attach($this->member->getKey(), ['is_owner' => false, 'is_default_tenant' => true]);
});

test('the administrator add owner refuses a user who already owns the tenant or is not a member', function (string $candidate) {
    $user = $candidate === 'an owner' ? $this->owner : User::factory()->create();

    expect(fn () => app(AddTenantOwner::class)->handle($this->tenant, $user))
        ->toThrow(InvalidArgumentException::class);
})->with(['an owner', 'a non member']);

test('the administrator can remove every previous owner once another owner was added', function () {
    $coOwner = User::factory()->create();
    $this->tenant->members()->attach($coOwner->getKey(), ['is_owner' => true, 'is_default_tenant' => true]);

    app(AddTenantOwner::class)->handle($this->tenant, $this->member);
    app(RemoveTenantOwner::class)->handle($this->tenant, $this->owner);
    app(RemoveTenantOwner::class)->handle($this->tenant, $coOwner);

    expect($this->tenant->members()->wherePivot('is_owner', true)->pluck('users.id')->all())->toBe([$this->member->getKey()]);
});
