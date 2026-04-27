<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DeviceGray implements ColorSpace
{
    public function pdfName(): string
    {
        return 'DeviceGray';
    }

    public function componentCount(): int
    {
        return 1;
    }
}
