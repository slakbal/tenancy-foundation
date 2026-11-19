<?php

namespace Slakbal\TenancyFoundation\Models\Concerns;

use Slakbal\TenancyFoundation\Support\Media\MediaType;
use Spatie\Image\Enums\Fit;

trait RegistersMediaTypes
{
    protected function registerMediaType(MediaType $type): void
    {
        $collection = $this->addMediaCollection($type->getCollection())
            ->useDisk($type->getDisk())
            ->storeConversionsOnDisk($type->getConversionsDisk())
            ->acceptsMimeTypes($type->getMimeTypes());

        if ($type->isSingleFile()) {
            $collection->singleFile();
        }

        $conversions = $type->getConversions();

        if ($conversions === []) {
            return;
        }

        $collection->registerMediaConversions(function () use ($type, $conversions): void {
            foreach ($conversions as $conversion) {
                $registered = $this->addMediaConversion($conversion['name'])
                    ->fit(Fit::Crop, $conversion['width'], $conversion['height'])
                    ->format($type->getConversionsFormat());

                if (! $conversion['queued']) {
                    $registered->nonQueued();
                }
            }
        });
    }
}
