<?php

declare(strict_types=1);

namespace Horde\Pdf;

use Horde\Pdf\TrueType\FontData;

final class DeferredFont implements Font
{
    public function __construct(
        private readonly FontData $data,
    ) {}

    public function pdfName(): string
    {
        return $this->data->postScriptName;
    }

    public function encoding(): FontEncoding
    {
        return FontEncoding::IdentityH;
    }

    public function style(): FontStyle
    {
        $isBold = $this->data->usWeightClass >= 700;
        $isItalic = $this->data->italicAngle !== 0
            || ($this->data->fsSelection & 0x0001) !== 0;

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
            $glyphId = $this->data->glyphIdForCodepoint($codepoint);
            $advanceWidth = $this->data->advanceWidth($glyphId);
            $width += $advanceWidth;
        }

        return $width * $size / $this->data->unitsPerEm;
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

    public function fontData(): FontData
    {
        return $this->data;
    }
}
