<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class IccProfile
{
    public function __construct(
        public readonly string $data,
        public readonly int $componentCount,
    ) {
        if ($componentCount < 1 || $componentCount > 4) {
            throw new PdfException('ICC profile component count must be 1-4');
        }
    }

    public function alternateSpace(): string
    {
        return match ($this->componentCount) {
            1 => 'DeviceGray',
            3 => 'DeviceRGB',
            4 => 'DeviceCMYK',
            default => 'DeviceRGB',
        };
    }
}
