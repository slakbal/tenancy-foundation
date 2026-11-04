<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\CreateTenantUser;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CreateTenantUser::class)->handle(tenant: Filament::getTenant(), data: $data, actor: Filament::auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
