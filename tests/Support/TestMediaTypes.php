<?php

namespace Slakbal\TenancyFoundation\Tests\Support;

use Slakbal\TenancyFoundation\Support\Media\MediaType;

enum TestMediaTypes: string implements MediaType
{
    case AVATAR_IMAGE = 'avatar_image';
    case RESUME_PDF = 'resume_pdf';

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
        return match ($this) {
            self::AVATAR_IMAGE => 'person/avatar',
            self::RESUME_PDF => 'person/resume',
        };
    }

    public function getMimeTypes(): array
    {
        return match ($this) {
            self::AVATAR_IMAGE => ['image/jpeg', 'image/png'],
            self::RESUME_PDF => ['application/pdf'],
        };
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
        return match ($this) {
            self::AVATAR_IMAGE => [
                ['name' => 'small', 'width' => 80, 'height' => 80, 'queued' => false],
                ['name' => 'large', 'width' => 600, 'height' => 600, 'queued' => true],
            ],
            self::RESUME_PDF => [],
        };
    }
}
