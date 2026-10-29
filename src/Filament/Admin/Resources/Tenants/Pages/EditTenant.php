<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages;

use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions\CancelTenantAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions\ReactivateTenantAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            CancelTenantAction::make()->successRedirectUrl(fn (Model $record): string => $this->tenantPageUrl('edit', $record)),
            ReactivateTenantAction::make()->successRedirectUrl(fn (Model $record): string => $this->tenantPageUrl('edit', $record)),
        ];
    }

    protected function tenantPageUrl(string $page, Model $record): string
    {
        return static::getResource()::getUrl($page, ['record' => $record]);
    }
}
