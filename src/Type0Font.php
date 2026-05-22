<?php

declare(strict_types=1);

namespace Horde\Pdf;

use Horde\Pdf\TrueType\FontData;
use Horde\Pdf\TrueType\FontParser;
use Horde\Pdf\TrueType\Subsetter;

final class Type0Font implements Font
{
    private readonly CidFont $cidFont;
    private readonly string $subsetPrefix;

    /**
     * @param array<int> $codepoints Unicode codepoints used in the document
     */
    public function __construct(
        private readonly FontData $fontData,
        array $codepoints,
        ?string $subsetPrefix = null,
    ) {
        $this->subsetPrefix = $subsetPrefix ?? self::generatePrefix();
        $subsetResult = Subsetter::subset($fontData, $codepoints);
        $this->cidFont = new CidFont($fontData, $subsetResult, $this->subsetPrefix);
    }

    public static function fromFile(string $path, array $codepoints): self
    {
        return new self(FontParser::parseFile($path), $codepoints);
    }

    public function pdfName(): string
    {
        return $this->cidFont->pdfName();
    }

    public function encoding(): FontEncoding
    {
        return FontEncoding::IdentityH;
    }

    public function style(): FontStyle
    {
        return $this->cidFont->style();
    }

    public function widthOfString(string $text, float $size): float
    {
        return $this->cidFont->widthOfString($text, $size);
    }

    public function encode(string $text): string
    {
        return $this->cidFont->encode($text);
    }

    public function requiresEmbedding(): bool
    {
        return true;
    }

    public function descendant(): CidFont
    {
        return $this->cidFont;
    }

    private static function generatePrefix(): string
    {
        $prefix = '';
        for ($i = 0; $i < 6; $i++) {
            $prefix .= chr(random_int(65, 90));
        }
        return $prefix;
    }
}
