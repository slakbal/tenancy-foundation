<?php

use Filament\Facades\Filament;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenant;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->tenant = app(CreateTenant::class)->handle(['name' => 'Academy', 'domain' => 'academy.example.com'], $this->owner);
});

test('the admin panel is served on the admin host only', function (string $host, int $status) {
    $this->get("http://{$host}/admin/login")->assertStatus($status);
})->with([
    'admin host' => ['backoffice.test', 200],
    'tenant host' => ['academy.example.com', 404],
    'app domain' => ['tenant.test', 404],
]);

test('the admin host is never resolved as a tenant, even when a tenant claims it', function () {
    $this->tenant->forceFill(['domain' => 'backoffice.test'])->save();
    $this->actingAs($this->owner, AuthGuard::TENANT->value);

    $this->get('http://backoffice.test/contact')->assertNotFound();

    expect(Filament::getTenant())->toBeNull();
});

test('a tenant user returns to the login page of the same host after logging out', function () {
    $this->actingAs($this->owner, AuthGuard::TENANT->value);

    $this->post('http://academy.example.com/logout')->assertRedirect('http://academy.example.com/login');
});

test('an admin returns to the admin login page after logging out', function () {
    $this->actingAs(Admin::factory()->create(), AuthGuard::ADMIN->value);

    $this->post('http://backoffice.test/admin/logout')->assertRedirect('http://backoffice.test/admin/login');
});
