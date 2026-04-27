<?php

declare(strict_types=1);

use Horde\Pdf\CoreFont;
use Horde\Pdf\Font;
use Horde\Pdf\FontEncoding;
use Horde\Pdf\FontStyle;
use Horde\Pdf\Type1Font;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Type1Font::class)]
class Type1FontTest extends TestCase
{
    public function testPdfNameDelegation(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $this->assertSame('Helvetica', $font->pdfName());
    }

    public function testImplementsFontInterface(): void
    {
        $font = CoreFont::Courier->toFont();
        $this->assertInstanceOf(Font::class, $font);
    }

    public function testRequiresEmbeddingFalse(): void
    {
        $font = CoreFont::Times->toFont();
        $this->assertFalse($font->requiresEmbedding());
    }

    public function testEncodeReturnsTextUnchanged(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $this->assertSame('Hello World', $font->encode('Hello World'));
    }

    public function testEncodingWinAnsiForMostFonts(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $this->assertSame(FontEncoding::WinAnsi, $font->encoding());

        $font = CoreFont::Courier->toFont();
        $this->assertSame(FontEncoding::WinAnsi, $font->encoding());

        $font = CoreFont::TimesBold->toFont();
        $this->assertSame(FontEncoding::WinAnsi, $font->encoding());
    }

    public function testEncodingSymbol(): void
    {
        $font = CoreFont::Symbol->toFont();
        $this->assertSame(FontEncoding::Symbol, $font->encoding());
    }

    public function testEncodingZapfDingbats(): void
    {
        $font = CoreFont::ZapfDingbats->toFont();
        $this->assertSame(FontEncoding::ZapfDingbats, $font->encoding());
    }

    public function testStyleRegular(): void
    {
        $this->assertSame(FontStyle::Regular, CoreFont::Helvetica->toFont()->style());
        $this->assertSame(FontStyle::Regular, CoreFont::Courier->toFont()->style());
        $this->assertSame(FontStyle::Regular, CoreFont::Times->toFont()->style());
    }

    public function testStyleBold(): void
    {
        $this->assertSame(FontStyle::Bold, CoreFont::HelveticaBold->toFont()->style());
        $this->assertSame(FontStyle::Bold, CoreFont::CourierBold->toFont()->style());
    }

    public function testStyleItalic(): void
    {
        $this->assertSame(FontStyle::Italic, CoreFont::HelveticaItalic->toFont()->style());
        $this->assertSame(FontStyle::Italic, CoreFont::TimesItalic->toFont()->style());
    }

    public function testStyleBoldItalic(): void
    {
        $this->assertSame(FontStyle::BoldItalic, CoreFont::HelveticaBoldItalic->toFont()->style());
        $this->assertSame(FontStyle::BoldItalic, CoreFont::CourierBoldItalic->toFont()->style());
    }

    public function testWidthOfStringCourier(): void
    {
        $font = CoreFont::Courier->toFont();
        $width = $font->widthOfString('Hi', 12.0);
        $this->assertEqualsWithDelta(600 * 2 * 12.0 / 1000.0, $width, 0.001);
    }

    public function testWidthOfStringHelvetica(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $widths = $font->widths();
        $expected = ($widths['H'] + $widths['i']) * 10.0 / 1000.0;
        $this->assertEqualsWithDelta($expected, $font->widthOfString('Hi', 10.0), 0.001);
    }

    public function testWidthOfEmptyString(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $this->assertSame(0.0, $font->widthOfString('', 12.0));
    }

    public function testCoreFontBridge(): void
    {
        $font = CoreFont::HelveticaBold->toFont();
        $this->assertSame(CoreFont::HelveticaBold, $font->coreFont());
    }
}
