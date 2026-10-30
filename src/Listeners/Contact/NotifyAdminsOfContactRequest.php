<?php

namespace Slakbal\TenancyFoundation\Listeners\Contact;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminPermissions;
use Slakbal\TenancyFoundation\Enums\Authorisation\AdminRoles;
use Slakbal\TenancyFoundation\Events\Contact\ContactRequestReceived;
use Spatie\Permission\PermissionRegistrar;

class NotifyAdminsOfContactRequest implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(ContactRequestReceived $event): void
    {
        $notification = config('tenancy-foundation.notifications.contact_received');

        if ($notification === null || ! config('tenancy-foundation.contact_requests.notify_admins')) {
            return;
        }

        Notification::send($this->recipients(), new $notification($event->contactRequest));
    }

    protected function recipients(): Collection
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->setPermissionsTeamId(config('tenancy-foundation.admin_permissions_team_id'));

        try {
            return config('tenancy-foundation.models.admin')::query()
                ->get()
                ->filter(fn ($admin): bool => $admin->hasRole(AdminRoles::SUPER_ADMIN) || $admin->checkPermissionTo(AdminPermissions::CONTACT_REQUEST_VIEW))
                ->values();
        } finally {
            $registrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
