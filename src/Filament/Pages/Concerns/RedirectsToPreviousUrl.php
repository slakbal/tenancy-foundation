<?php

namespace Slakbal\TenancyFoundation\Filament\Pages\Concerns;

trait RedirectsToPreviousUrl
{
    protected function getRedirectUrl(): ?string
    {
        if (! config('tenancy-foundation.redirects.edit_returns_to_previous')) {
            return parent::getRedirectUrl();
        }

        return $this->previousUrl ?? static::getResource()::getUrl('index');
    }
}
