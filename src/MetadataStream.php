<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class MetadataStream
{
    public function __construct(
        public readonly string $xml,
    ) {}
}
