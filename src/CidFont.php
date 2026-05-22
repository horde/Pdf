<?php

declare(strict_types=1);

namespace Horde\Pdf;

use Horde\Pdf\TrueType\FontData;
use Horde\Pdf\TrueType\SubsetResult;

final class CidFont implements Font
{
    private readonly string $postScriptName;

    public function __construct(
        private readonly FontData $fontData,
        private readonly SubsetResult $subsetResult,
        private readonly string $subsetPrefix,
    ) {
        $this->postScriptName = $subsetPrefix . '+' . $fontData->postScriptName;
    }

    public function pdfName(): string
    {
        return $this->postScriptName;
    }

    public function encoding(): FontEncoding
    {
        return FontEncoding::IdentityH;
    }

    public function style(): FontStyle
    {
        $isBold = $this->fontData->usWeightClass >= 700;
        $isItalic = $this->fontData->italicAngle !== 0
            || ($this->fontData->fsSelection & 0x0001) !== 0;

        if ($isBold && $isItalic) {
            return FontStyle::BoldItalic;
        }
        if ($isBold) {
            return FontStyle::Bold;
        }
        if ($isItalic) {
            return FontStyle::Italic;
        }
        return FontStyle::Regular;
    }

    public function widthOfString(string $text, float $size): float
    {
        $width = 0.0;
        $chars = mb_str_split($text, 1, 'UTF-8');
        foreach ($chars as $char) {
            $codepoint = mb_ord($char, 'UTF-8');
            $glyphId = $this->fontData->glyphIdForCodepoint($codepoint);
            $advanceWidth = $this->fontData->advanceWidth($glyphId);
            $width += $advanceWidth;
        }

        return $width * $size / $this->fontData->unitsPerEm;
    }

    public function encode(string $text): string
    {
        $encoded = '';
        $chars = mb_str_split($text, 1, 'UTF-8');
        foreach ($chars as $char) {
            $codepoint = mb_ord($char, 'UTF-8');
            $encoded .= chr(($codepoint >> 8) & 0xFF) . chr($codepoint & 0xFF);
        }
        return $encoded;
    }

    public function requiresEmbedding(): bool
    {
        return true;
    }

    public function subsetFontProgram(): string
    {
        return $this->subsetResult->data;
    }

    public function defaultWidth(): int
    {
        return $this->fontData->advanceWidth(0);
    }

    /**
     * @return array<int, array{int, int}> Array of [cid, width] entries for /W array
     */
    public function cidWidths(): array
    {
        $entries = [];
        foreach ($this->subsetResult->cidToGid as $cid => $newGid) {
            if ($cid === 0) {
                continue;
            }
            $width = $this->subsetResult->widths[$newGid] ?? 0;
            $entries[] = [$cid, (int) round($width * 1000 / $this->fontData->unitsPerEm)];
        }
        return $entries;
    }

    /**
     * @return array{ascent: int, descent: int, capHeight: int, flags: int, italicAngle: int, stemV: int, bbox: array{int, int, int, int}}
     */
    public function fontDescriptorMetrics(): array
    {
        $scale = 1000 / $this->fontData->unitsPerEm;
        $flags = 0x0004;
        if ($this->fontData->isFixedPitch) {
            $flags |= 0x0001;
        }
        if ($this->fontData->italicAngle !== 0) {
            $flags |= 0x0040;
        }

        return [
            'ascent' => (int) round($this->fontData->ascender * $scale),
            'descent' => (int) round($this->fontData->descender * $scale),
            'capHeight' => (int) round($this->fontData->capHeight * $scale),
            'flags' => $flags,
            'italicAngle' => $this->fontData->italicAngle,
            'stemV' => (int) round(50 + (int) abs($this->fontData->usWeightClass - 400) * 0.22),
            'bbox' => [
                (int) round($this->fontData->xMin * $scale),
                (int) round($this->fontData->yMin * $scale),
                (int) round($this->fontData->xMax * $scale),
                (int) round($this->fontData->yMax * $scale),
            ],
        ];
    }

    public function toUnicodeCMap(): string
    {
        $entries = [];
        foreach ($this->subsetResult->cidToGid as $cid => $newGid) {
            if ($cid === 0) {
                continue;
            }
            $entries[$cid] = $cid;
        }
        ksort($entries);

        $cmap = "/CIDInit /ProcSet findresource begin\n";
        $cmap .= "12 dict begin\n";
        $cmap .= "begincmap\n";
        $cmap .= "/CIDSystemInfo\n";
        $cmap .= "<< /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n";
        $cmap .= "/CMapName /Adobe-Identity-UCS def\n";
        $cmap .= "/CMapType 2 def\n";
        $cmap .= "1 begincodespacerange\n";
        $cmap .= "<0000> <FFFF>\n";
        $cmap .= "endcodespacerange\n";

        $chunks = array_chunk($entries, 100, true);
        foreach ($chunks as $chunk) {
            $cmap .= count($chunk) . " beginbfchar\n";
            foreach ($chunk as $cid => $unicode) {
                $cmap .= sprintf("<%04X> <%04X>\n", $cid, $unicode);
            }
            $cmap .= "endbfchar\n";
        }

        $cmap .= "endcmap\n";
        $cmap .= "CMapName currentdict /CMap defineresource pop\n";
        $cmap .= "end\n";
        $cmap .= "end\n";

        return $cmap;
    }

    public function cidToGidMapData(): string
    {
        $maxCid = 0;
        foreach ($this->subsetResult->cidToGid as $cid => $gid) {
            if ($cid > $maxCid) {
                $maxCid = $cid;
            }
        }

        $map = str_repeat("\x00\x00", $maxCid + 1);
        foreach ($this->subsetResult->cidToGid as $cid => $gid) {
            $map[$cid * 2] = chr(($gid >> 8) & 0xFF);
            $map[$cid * 2 + 1] = chr($gid & 0xFF);
        }

        return $map;
    }

    public function fontData(): FontData
    {
        return $this->fontData;
    }
}
