<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class CustomPageFormat
{
    public function __construct(
        public readonly float $width,
        public readonly float $height,
    ) {}

    /**
     * @return array{float, float} Width and height (in the caller's unit, not points).
     */
    public function dimensions(): array
    {
        return [$this->width, $this->height];
    }
}
