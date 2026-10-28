<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions\CancelTenantAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\Actions\ReactivateTenantAction;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\Tenants\TenantResource;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->button()->hiddenLabel(false)->tooltip(null), // overrides the defaults
            CancelTenantAction::make()->successRedirectUrl(fn (Model $record): string => $this->tenantPageUrl('view', $record)),
            ReactivateTenantAction::make()->successRedirectUrl(fn (Model $record): string => $this->tenantPageUrl('view', $record)),
        ];
    }

    protected function tenantPageUrl(string $page, Model $record): string
    {
        return static::getResource()::getUrl($page, ['record' => $record]);
    }
}
