<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class SeparationColorSpace implements ColorSpace
{
    public function __construct(
        public readonly string $colorantName,
        public readonly ColorSpace $alternateSpace,
        public readonly PdfFunction $tintTransform,
    ) {}

    public function pdfName(): string
    {
        return 'Separation';
    }

    public function componentCount(): int
    {
        return 1;
    }
}
