<?php

namespace Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\ChangeMemberStatusAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\LeaveTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RemoveFromTenantAction;
use Slakbal\TenancyFoundation\Filament\Components\Tables\Actions\RestoreMemberAction;
use Slakbal\TenancyFoundation\Filament\Tenant\Resources\Users\UserResource;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->hidden(fn (): bool => $this->getRecord()->trashed()),
            ChangeMemberStatusAction::deactivate(),
            ChangeMemberStatusAction::activate(),
            RestoreMemberAction::make()->successRedirectUrl(fn (): string => static::getResource()::getUrl('view', ['record' => $this->getRecord()])),
            RemoveFromTenantAction::make()->successRedirectUrl(fn (): string => static::getResource()::getUrl('index')),
            LeaveTenantAction::make(),
        ];
    }
}
