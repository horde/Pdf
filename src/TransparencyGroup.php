<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class TransparencyGroup
{
    public function __construct(
        public readonly ?ColorSpace $colorSpace = null,
        public readonly bool $isolated = false,
        public readonly bool $knockout = false,
    ) {}
}
