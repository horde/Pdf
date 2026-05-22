<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\PdfWriter;
use Horde\Pdf\Type0Font;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Type0Font::class)]
final class TrueTypePdfOutputTest extends TestCase
{
    private string $fontPath;

    protected function setUp(): void
    {
        $this->fontPath = __DIR__ . '/fixtures/roboto-light.ttf';
        if (!file_exists($this->fontPath)) {
            $this->markTestSkipped('Font fixture not available');
        }
    }

    #[Test]
    public function outputContainsType0FontDictionary(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/Subtype /Type0', $pdf);
    }

    #[Test]
    public function outputContainsIdentityHEncoding(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/Encoding /Identity-H', $pdf);
    }

    #[Test]
    public function outputContainsDescendantFonts(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/DescendantFonts [', $pdf);
    }

    #[Test]
    public function outputContainsCidFontType2(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/Subtype /CIDFontType2', $pdf);
    }

    #[Test]
    public function outputContainsFontDescriptor(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/Type /FontDescriptor', $pdf);
    }

    #[Test]
    public function outputContainsFontFile2Reference(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/FontFile2', $pdf);
    }

    #[Test]
    public function outputContainsToUnicodeReference(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/ToUnicode', $pdf);
    }

    #[Test]
    public function outputContainsCidToGidMap(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/CIDToGIDMap', $pdf);
    }

    #[Test]
    public function outputContainsCidSystemInfo(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/CIDSystemInfo', $pdf);
    }

    #[Test]
    public function outputContainsSubsetPrefix(): void
    {
        $codepoints = $this->codepointsFromString('Hello');
        $font = new Type0Font(
            \Horde\Pdf\TrueType\FontParser::parseFile($this->fontPath),
            $codepoints,
            'ABCDEF',
        );

        $writer = new PdfWriter();
        $writer->addPage();
        $writer->setTrueTypeFont($font, 12.0);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/BaseFont /ABCDEF+', $pdf);
    }

    #[Test]
    public function outputContainsWidthsArray(): void
    {
        $pdf = $this->createPdfWithTrueType('Hello');
        $this->assertStringContainsString('/W [', $pdf);
    }

    #[Test]
    public function outputContainsHexEncodedText(): void
    {
        $codepoints = $this->codepointsFromString('Hi');
        $font = Type0Font::fromFile($this->fontPath, $codepoints);

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setTrueTypeFont($font, 12.0);
        $writer->text(10.0, 20.0, 'Hi');
        $pdf = $writer->getOutput();

        $this->assertMatchesRegularExpression('/<[0-9A-F]+>\s+Tj/', $pdf);
    }

    #[Test]
    public function outputIsValidPdfStructure(): void
    {
        $pdf = $this->createPdfWithTrueType('Test PDF');
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertStringContainsString('xref', $pdf);
        $this->assertStringContainsString('trailer', $pdf);
    }

    #[Test]
    public function unicodeTextProducesCorrectHex(): void
    {
        $text = "AB";
        $codepoints = $this->codepointsFromString($text);
        $font = Type0Font::fromFile($this->fontPath, $codepoints);

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setTrueTypeFont($font, 12.0);
        $writer->text(10.0, 20.0, $text);
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('<00410042>', $pdf);
    }

    private function createPdfWithTrueType(string $text): string
    {
        $codepoints = $this->codepointsFromString($text);
        $font = Type0Font::fromFile($this->fontPath, $codepoints);

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setTrueTypeFont($font, 12.0);
        $writer->text(10.0, 20.0, $text);

        return $writer->getOutput();
    }

    /**
     * @return array<int>
     */
    private function codepointsFromString(string $text): array
    {
        $codepoints = [];
        $chars = mb_str_split($text, 1, 'UTF-8');
        foreach ($chars as $char) {
            $codepoints[] = mb_ord($char, 'UTF-8');
        }
        return $codepoints;
    }
}
