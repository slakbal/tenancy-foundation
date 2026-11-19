<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Slakbal\TenancyFoundation\Support\Media\MediaType;

class MediaTypeImageColumn
{
    public static function make(string $name, MediaType $type): SpatieMediaLibraryImageColumn
    {
        return SpatieMediaLibraryImageColumn::make($name)
            ->label(__('Image'))
            ->collection($type->getCollection())
            ->conversion($type->getConversions()[0]['name'])
            ->imageSize(40);
    }
}
