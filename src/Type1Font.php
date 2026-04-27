<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class Type1Font implements Font
{
    /** @var array<string, int>|null */
    private ?array $widths = null;

    public function __construct(
        private readonly CoreFont $coreFont,
    ) {}

    public function pdfName(): string
    {
        return $this->coreFont->pdfName();
    }

    public function encoding(): FontEncoding
    {
        return match ($this->coreFont) {
            CoreFont::Symbol => FontEncoding::Symbol,
            CoreFont::ZapfDingbats => FontEncoding::ZapfDingbats,
            default => FontEncoding::WinAnsi,
        };
    }

    public function style(): FontStyle
    {
        return match ($this->coreFont) {
            CoreFont::CourierBold, CoreFont::HelveticaBold, CoreFont::TimesBold => FontStyle::Bold,
            CoreFont::CourierItalic, CoreFont::HelveticaItalic, CoreFont::TimesItalic => FontStyle::Italic,
            CoreFont::CourierBoldItalic, CoreFont::HelveticaBoldItalic, CoreFont::TimesBoldItalic => FontStyle::BoldItalic,
            default => FontStyle::Regular,
        };
    }

    public function widthOfString(string $text, float $size): float
    {
        $widths = $this->widths();
        $total = 0;

        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $total += $widths[$text[$i]] ?? 0;
        }

        return $total * $size / 1000.0;
    }

    public function encode(string $text): string
    {
        return $text;
    }

    public function requiresEmbedding(): bool
    {
        return false;
    }

    public function coreFont(): CoreFont
    {
        return $this->coreFont;
    }

    /**
     * @return array<string, int>
     */
    public function widths(): array
    {
        if ($this->widths === null) {
            $this->widths = $this->coreFont->widths();
        }

        return $this->widths;
    }
}
