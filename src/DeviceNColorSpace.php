<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DeviceNColorSpace implements ColorSpace
{
    /** @param array<string> $colorantNames */
    public function __construct(
        public readonly array $colorantNames,
        public readonly ColorSpace $alternateSpace,
        public readonly PdfFunction $tintTransform,
    ) {}

    public function pdfName(): string
    {
        return 'DeviceN';
    }

    public function componentCount(): int
    {
        return count($this->colorantNames);
    }
}
