<?php

namespace Slakbal\TenancyFoundation\Support\Media;

interface HasTableImage
{
    public function getTableImageSize(): int;

    public function getTableConversion(): string;
}
