<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class SeparationColor
{
    public function __construct(
        private readonly SeparationColorSpace $colorSpace,
        private readonly float $tint,
    ) {}

    public function colorSpace(): SeparationColorSpace
    {
        return $this->colorSpace;
    }

    public function toPdfFillString(string $resourceName): string
    {
        return sprintf('/%s cs %.3F sc', $resourceName, $this->tint);
    }

    public function toPdfStrokeString(string $resourceName): string
    {
        return sprintf('/%s CS %.3F SC', $resourceName, $this->tint);
    }
}
