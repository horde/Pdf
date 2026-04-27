<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface ColorSpace
{
    public function pdfName(): string;

    public function componentCount(): int;
}
