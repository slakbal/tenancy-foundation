<?php

namespace Slakbal\TenancyFoundation\Filament\Components\Forms;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Slakbal\TenancyFoundation\Support\Media\MediaType;

class MediaTypeUpload
{
    public static function make(string $name, MediaType $type): SpatieMediaLibraryFileUpload
    {
        $isImage = collect($type->getMimeTypes())->every(fn (string $mime): bool => str_starts_with($mime, 'image/'));

        $upload = SpatieMediaLibraryFileUpload::make($name)
            ->label($isImage ? __('Image') : __('File'))
            ->collection($type->getCollection())
            ->columnSpanFull();

        if ($isImage) {
            $upload->image()->acceptedFileTypes($type->getMimeTypes())->imageEditor();
        } else {
            $upload->acceptedFileTypes($type->getMimeTypes())->downloadable()->openable();
        }

        if (! $type->isSingleFile()) {
            $upload->multiple()->reorderable();
        }

        return $upload;
    }
}
