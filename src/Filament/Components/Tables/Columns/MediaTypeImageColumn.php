<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Tables\Columns;

use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Slakbal\TenancyFoundation\Support\Media\HasTableImage;
use Slakbal\TenancyFoundation\Support\Media\MediaType;

class MediaTypeImageColumn
{
    public static function make(string $name, MediaType $type): SpatieMediaLibraryImageColumn
    {
        return SpatieMediaLibraryImageColumn::make($name)
            ->label('')
            ->collection($type->getCollection())
            ->conversion($type instanceof HasTableImage ? $type->getTableConversion() : $type->getConversions()[0]['name'])
            ->imageSize($type instanceof HasTableImage ? $type->getTableImageSize() : 40)
            ->extraImgAttributes(['class' => 'rounded-lg object-cover shadow-sm'])
            ->grow(false);
    }
}
