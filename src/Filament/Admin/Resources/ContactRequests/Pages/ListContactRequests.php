<?php

namespace Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Pages;

use Filament\Resources\Pages\ListRecords;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\ContactRequestResource;
use Slakbal\TenancyFoundation\Filament\Admin\Resources\ContactRequests\Widgets\ContactRequestStats;

class ListContactRequests extends ListRecords
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ContactRequestStats::class,
        ];
    }
}
