<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DeviceRgb implements ColorSpace
{
    public function pdfName(): string
    {
        return 'DeviceRGB';
    }

    public function componentCount(): int
    {
        return 3;
    }
}
