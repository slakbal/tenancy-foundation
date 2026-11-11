<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages;

use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Actions\Tenant\UpdateTenantUser;
use Slakbal\TenancyFoundation\Enums\Authorisation\TenantRoles;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ChangeMemberStatusAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveFromTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RestoreMemberAction;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            ChangeMemberStatusAction::deactivate(),
            ChangeMemberStatusAction::activate(),
            RestoreMemberAction::make()->successRedirectUrl(fn (): string => static::getResource()::getUrl('view', ['record' => $this->getRecord()])),
            RemoveFromTenantAction::make()->successRedirectUrl(fn (): string => static::getResource()::getUrl('index')),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['tenant_roles'] = $this->getRecord()->roles
            ->pluck('name')
            ->reject(fn (string $role): bool => $role === TenantRoles::OWNER->value)
            ->values()
            ->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateTenantUser::class)->handle($record, Filament::getTenant(), $data, Filament::auth()->user());
    }
}
