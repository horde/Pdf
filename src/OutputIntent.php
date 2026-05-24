<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class OutputIntent
{
    public function __construct(
        public readonly string $subtype = 'GTS_PDFA1',
        public readonly string $outputConditionIdentifier = 'sRGB',
        public readonly string $registryName = 'http://www.color.org',
        public readonly string $info = 'sRGB IEC61966-2.1',
    ) {}
}
