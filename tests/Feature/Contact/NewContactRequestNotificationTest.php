<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Actions\Contact\CreateContactRequest;
use Slakbal\TenancyFoundation\Enums\AuthGuard;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Enums\ContactSource;
use Slakbal\TenancyFoundation\Events\ContactRequestReceived;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Slakbal\TenancyFoundation\Models\Admin;
use Slakbal\TenancyFoundation\Models\ContactRequest;
use Slakbal\TenancyFoundation\Notifications\NewContactRequest;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function contactNotifiableAdmin(?AdminPermissions $permission = null, ?AdminRoles $role = null): Admin
{
    setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));
    $admin = Admin::factory()->create();

    if ($permission) {
        Permission::findOrCreate($permission->value, AuthGuard::ADMIN->value);
        $admin->givePermissionTo($permission);
    }

    if ($role) {
        Role::findOrCreate($role->value, AuthGuard::ADMIN->value);
        $admin->assignRole($role);
    }

    return $admin;
}

function receiveContact(): ContactRequest
{
    return app(CreateContactRequest::class)->handle(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hello there'], ContactSource::PUBLIC);
}

test('creating a contact request dispatches the received event once', function () {
    Event::fake([ContactRequestReceived::class]);

    $request = receiveContact();

    Event::assertDispatchedTimes(ContactRequestReceived::class, 1);
    Event::assertDispatched(ContactRequestReceived::class, fn ($event): bool => $event->contactRequest->is($request));
});

test('admins who may view contact requests and super admins are notified, others are not', function () {
    Notification::fake();
    $viewer = contactNotifiableAdmin(AdminPermissions::CONTACT_REQUEST_VIEW);
    $super = contactNotifiableAdmin(role: AdminRoles::SUPER_ADMIN);
    $other = contactNotifiableAdmin(AdminPermissions::TENANT_VIEW);
    $nobody = contactNotifiableAdmin();

    $request = receiveContact();

    Notification::assertSentTo([$viewer, $super], NewContactRequest::class, fn ($notification): bool => $notification->contactRequest->is($request));
    Notification::assertNotSentTo([$other, $nobody], NewContactRequest::class);
});

test('the notification is stored for the filament bell and links to the request in the admin panel', function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $viewer = contactNotifiableAdmin(AdminPermissions::CONTACT_REQUEST_VIEW);

    $request = receiveContact();

    $stored = $viewer->notifications()->sole();

    expect($stored->data['title'])->toBe('New contact request')
        ->and($stored->data['body'])->toContain('Jane Doe')->toContain('Hello there')
        ->and($stored->data['actions'][0]['url'])->toBe(ContactRequestResource::getUrl('edit', ['record' => $request], panel: 'admin'))
        ->and($stored->data['actions'][0]['shouldMarkAsRead'])->toBeTrue()
        ->and($stored->read_at)->toBeNull();
});

test('notifications can be switched off through config', function (array $config) {
    $viewer = contactNotifiableAdmin(AdminPermissions::CONTACT_REQUEST_VIEW);
    config($config);

    receiveContact();

    expect($viewer->notifications()->count())->toBe(0);
})->with([
    'admins not notified' => [['tenancy-foundation.contact_requests.notify_admins' => false]],
    'no notification class' => [['tenancy-foundation.notifications.contact_received' => null]],
]);

test('the notification can be replaced through config', function () {
    $viewer = contactNotifiableAdmin(AdminPermissions::CONTACT_REQUEST_VIEW);
    config(['tenancy-foundation.notifications.contact_received' => CustomNewContactRequest::class]);

    receiveContact();

    expect($viewer->notifications()->sole()->type)->toBe(CustomNewContactRequest::class);
});

test('the admin panel has database notifications enabled', function () {
    expect(Filament::getPanel('admin')->hasDatabaseNotifications())->toBeTrue();
});

test('the permission team context is restored after notifying', function () {
    contactNotifiableAdmin(AdminPermissions::CONTACT_REQUEST_VIEW);
    setPermissionsTeamId(99);

    receiveContact();

    expect(getPermissionsTeamId())->toBe(99);
});

class CustomNewContactRequest extends NewContactRequest {}
