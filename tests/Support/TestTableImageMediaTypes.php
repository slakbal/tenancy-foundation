<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Slakbal\TenancyFoundation\Support\Media\HasTableImage;
use Slakbal\TenancyFoundation\Support\Media\MediaType;

enum TestTableImageMediaTypes: string implements HasTableImage, MediaType
{
    case BANNER_IMAGE = 'banner_image';

    public function getCollection(): string
    {
        return $this->value;
    }

    public function getDisk(): string
    {
        return 'public';
    }

    public function getConversionsDisk(): string
    {
        return 'public';
    }

    public function getDiskPath(): string
    {
        return 'banner';
    }

    public function getMimeTypes(): array
    {
        return ['image/png'];
    }

    public function isSingleFile(): bool
    {
        return true;
    }

    public function getConversionsFormat(): string
    {
        return 'webp';
    }

    public function getConversions(): array
    {
        return [
            ['name' => 'small', 'width' => 80, 'height' => 80, 'queued' => false],
            ['name' => 'large', 'width' => 600, 'height' => 600, 'queued' => true],
        ];
    }

    public function getTableImageSize(): int
    {
        return 88;
    }

    public function getTableConversion(): string
    {
        return 'large';
    }
}
