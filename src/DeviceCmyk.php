<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DeviceCmyk implements ColorSpace
{
    public function pdfName(): string
    {
        return 'DeviceCMYK';
    }

    public function componentCount(): int
    {
        return 4;
    }
}
