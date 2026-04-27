<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class Destination
{
    public function __construct(
        public readonly Page $page,
        public readonly float $top = 0.0,
        public readonly ?float $left = null,
        public readonly ?float $zoom = null,
    ) {}
}
