<?php

namespace Slakbal\TenancyFoundation\Support\Media;

interface MediaType
{
    public function getCollection(): string;

    public function getDisk(): string;

    public function getConversionsDisk(): string;

    public function getDiskPath(): string;

    public function getMimeTypes(): array;

    public function isSingleFile(): bool;

    public function getConversionsFormat(): string;

    public function getConversions(): array;
}
