<?php

declare(strict_types=1);

namespace Horde\Pdf\TrueType;

final class FontData
{
    public function __construct(
        public readonly int $unitsPerEm,
        public readonly int $indexToLocFormat,
        public readonly int $numGlyphs,
        public readonly int $numberOfHMetrics,
        public readonly int $ascender,
        public readonly int $descender,
        public readonly int $capHeight,
        public readonly int $italicAngle,
        public readonly int $usWeightClass,
        public readonly int $fsSelection,
        public readonly int $underlinePosition,
        public readonly int $underlineThickness,
        public readonly bool $isFixedPitch,
        public readonly string $postScriptName,
        public readonly string $fontFamily,
        public readonly string $fontSubfamily,
        public readonly int $xMin,
        public readonly int $yMin,
        public readonly int $xMax,
        public readonly int $yMax,
        public readonly int $sTypoAscender,
        public readonly int $sTypoDescender,
        /** @var array<int, int> codepoint → glyph ID */
        public readonly array $cmap,
        /** @var array<int, int> glyph ID → advance width */
        public readonly array $hmtx,
        /** @var array<int, int> glyph offsets */
        public readonly array $loca,
        public readonly string $glyfRaw,
        public readonly string $rawData,
        /** @var array<string, array{offset: int, length: int}> table directory */
        public readonly array $tableDirectory,
    ) {}

    public function glyphIdForCodepoint(int $codepoint): int
    {
        return $this->cmap[$codepoint] ?? 0;
    }

    public function advanceWidth(int $glyphId): int
    {
        return $this->hmtx[$glyphId] ?? ($this->hmtx[$this->numberOfHMetrics - 1] ?? 0);
    }
}
