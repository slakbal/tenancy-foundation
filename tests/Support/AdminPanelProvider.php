<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Filament\Http\Middleware\Authenticate;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Slakbal\TenancyFoundation\Enums\FilamentPanel;
use Slakbal\TenancyFoundation\Plugins\AdminPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id(FilamentPanel::ADMIN->getPanelId())
            ->strictAuthorization()
            ->path(FilamentPanel::ADMIN->getPath())
            ->authGuard(FilamentPanel::ADMIN->getAuthGuard())
            ->login()
            ->middleware([EncryptCookies::class, StartSession::class])
            ->authMiddleware([Authenticate::class])
            ->plugins([AdminPlugin::make()]);
    }
}
