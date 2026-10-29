<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\CreateAdminUser as CreateAdminUserAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\AdminUsers\AdminUserResource;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateAdminUserAction::class)->handle($data, Filament::auth()->user());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Admin created. An email to set a password has been sent.');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
