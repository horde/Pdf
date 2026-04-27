<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ImageXObject
{
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly ColorSpace $colorSpace,
        public readonly int $bitsPerComponent,
        public readonly string $filter,
        public readonly string $data,
        public readonly ?string $decodeParms = null,
        public readonly ?string $palette = null,
        public readonly ?array $transparency = null,
    ) {}
}
