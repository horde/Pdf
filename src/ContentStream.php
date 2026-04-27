<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ContentStream
{
    public function __construct(
        public readonly string $operators,
        public readonly ResourceDictionary $resources,
    ) {}
}
