<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Plugins\TenantPlugin;

class TenantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->strictAuthorization()
            ->id(FilamentPanel::TENANT->getPanelId())
            ->path(FilamentPanel::TENANT->getPath())
            ->authGuard(FilamentPanel::TENANT->getAuthGuard())
            ->login()
            ->middleware([EncryptCookies::class, StartSession::class])
            ->authMiddleware([Authenticate::class], isPersistent: true)
            ->plugins([TenantPlugin::make()]);
    }
}
