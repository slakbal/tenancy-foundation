<?php

namespace Slakbal\TenancyFoundation\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;

class MediaPathGenerator extends DefaultPathGenerator
{
    protected function getBasePath(Media $media): string
    {
        $types = config('tenancy-foundation.media.type_enum');

        return $types::from($media->collection_name)->getDiskPath().'/'.parent::getBasePath($media);
    }
}
