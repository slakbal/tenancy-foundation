<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Forms;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Slakbal\TenancyFoundation\Support\Media\MediaType;

class MediaTypeUpload
{
    public static function make(string $name, MediaType $type): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make($name)
            ->label(__('Image'))
            ->collection($type->getCollection())
            ->image()
            ->acceptedFileTypes($type->getMimeTypes())
            ->imageEditor()
            ->columnSpanFull();
    }
}
