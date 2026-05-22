<?php

declare(strict_types=1);

namespace Horde\Pdf\TrueType;

final class SubsetResult
{
    public function __construct(
        public readonly string $data,
        /** @var array<int, int> original GID → new GID */
        public readonly array $glyphMap,
        /** @var array<int, int> new GID → advance width in font units */
        public readonly array $widths,
        /** @var array<int, int> CID (codepoint) → new GID */
        public readonly array $cidToGid,
    ) {}
}
