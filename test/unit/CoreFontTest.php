<?php

declare(strict_types=1);

use Horde\Pdf\CoreFont;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoreFont::class)]
class CoreFontTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $this->assertCount(14, CoreFont::cases());
    }

    public function testPdfNames(): void
    {
        $this->assertSame('Courier', CoreFont::Courier->pdfName());
        $this->assertSame('Courier-Bold', CoreFont::CourierBold->pdfName());
        $this->assertSame('Helvetica', CoreFont::Helvetica->pdfName());
        $this->assertSame('Times-Roman', CoreFont::Times->pdfName());
        $this->assertSame('Symbol', CoreFont::Symbol->pdfName());
        $this->assertSame('ZapfDingbats', CoreFont::ZapfDingbats->pdfName());
    }

    public function testFamilies(): void
    {
        $this->assertSame('courier', CoreFont::Courier->family());
        $this->assertSame('courier', CoreFont::CourierBold->family());
        $this->assertSame('helvetica', CoreFont::Helvetica->family());
        $this->assertSame('times', CoreFont::Times->family());
        $this->assertSame('symbol', CoreFont::Symbol->family());
        $this->assertSame('zapfdingbats', CoreFont::ZapfDingbats->family());
    }

    public function testCourierWidthsAreUniform(): void
    {
        $widths = CoreFont::Courier->widths();
        $this->assertNotEmpty($widths);
        foreach ($widths as $w) {
            $this->assertSame(600, $w);
        }
    }

    public function testHelveticaWidthsVary(): void
    {
        $widths = CoreFont::Helvetica->widths();
        $this->assertNotEmpty($widths);
        $this->assertSame(278, $widths[' ']);
        $this->assertSame(667, $widths['A']);
    }

    public function testAllFontsHaveWidths(): void
    {
        foreach (CoreFont::cases() as $font) {
            $widths = $font->widths();
            $this->assertNotEmpty($widths, "Font {$font->value} should have width data");
            $this->assertGreaterThan(200, count($widths), "Font {$font->value} should have 256 character widths");
        }
    }

    public function testFromStringValue(): void
    {
        $this->assertSame(CoreFont::Courier, CoreFont::from('courier'));
        $this->assertSame(CoreFont::HelveticaBoldItalic, CoreFont::from('helveticaBI'));
    }

    public function testFromFamilyStyleTimes(): void
    {
        [$font, $underline] = CoreFont::fromFamilyStyle('Times', '');
        $this->assertSame(CoreFont::Times, $font);
        $this->assertFalse($underline);
    }

    public function testFromFamilyStyleTimesBold(): void
    {
        [$font, $underline] = CoreFont::fromFamilyStyle('Times', 'B');
        $this->assertSame(CoreFont::TimesBold, $font);
        $this->assertFalse($underline);
    }

    public function testFromFamilyStyleArialAlias(): void
    {
        [$font,] = CoreFont::fromFamilyStyle('Arial', '');
        $this->assertSame(CoreFont::Helvetica, $font);
    }

    public function testFromFamilyStyleBoldItalicNormalization(): void
    {
        [$font,] = CoreFont::fromFamilyStyle('Helvetica', 'IB');
        $this->assertSame(CoreFont::HelveticaBoldItalic, $font);
    }

    public function testFromFamilyStyleUnderlineFlag(): void
    {
        [$font, $underline] = CoreFont::fromFamilyStyle('Courier', 'BU');
        $this->assertSame(CoreFont::CourierBold, $font);
        $this->assertTrue($underline);
    }

    public function testFromFamilyStyleSymbolIgnoresStyle(): void
    {
        [$font,] = CoreFont::fromFamilyStyle('Symbol', 'B');
        $this->assertSame(CoreFont::Symbol, $font);
    }

    public function testFromFamilyStyleCaseInsensitive(): void
    {
        [$font,] = CoreFont::fromFamilyStyle('TIMES', 'bi');
        $this->assertSame(CoreFont::TimesBoldItalic, $font);
    }

    public function testFromFamilyStyleUnknownThrows(): void
    {
        $this->expectException(ValueError::class);
        CoreFont::fromFamilyStyle('UnknownFont', '');
    }
}
