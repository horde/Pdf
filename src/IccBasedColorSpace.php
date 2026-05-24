<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class IccBasedColorSpace implements ColorSpace
{
    public function __construct(
        public readonly IccProfile $profile,
    ) {}

    public function pdfName(): string
    {
        return 'ICCBased';
    }

    public function componentCount(): int
    {
        return $this->profile->componentCount;
    }
}
