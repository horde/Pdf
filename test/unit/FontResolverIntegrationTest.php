<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\CoreFontProvider;
use Horde\Pdf\FontResolver;
use Horde\Pdf\FontStyle;
use Horde\Pdf\PdfWriter;
use Horde\Pdf\TrueTypeFontProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontResolver::class)]
final class FontResolverIntegrationTest extends TestCase
{
    private string $fixtureDir;
    private FontResolver $resolver;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures';
        if (!file_exists($this->fixtureDir . '/roboto-light.ttf')) {
            $this->markTestSkipped('Font fixture not available');
        }

        $this->resolver = new FontResolver(
            new TrueTypeFontProvider($this->fixtureDir),
            new CoreFontProvider(),
        );
    }

    #[Test]
    public function setFontWithTrueTypeProducesEmbeddedFont(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Roboto Light', FontStyle::Regular, 12.0);
        $writer->text(10.0, 20.0, 'Hello World');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/Subtype /Type0', $pdf);
        $this->assertStringContainsString('/Encoding /Identity-H', $pdf);
        $this->assertStringContainsString('/Subtype /CIDFontType2', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
    }

    #[Test]
    public function setFontWithCoreFontProducesType1(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Helvetica', FontStyle::Bold, 14.0);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/Subtype /Type1', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
    }

    #[Test]
    public function unknownFontFallsBackToHelvetica(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('NonExistentFont', FontStyle::Regular, 12.0);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
    }

    #[Test]
    public function trueTypeFontSubsetsToUsedCharacters(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Roboto Light', FontStyle::Regular, 12.0);
        $writer->text(10.0, 20.0, 'AB');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('<00410042>', $pdf);
        $this->assertStringContainsString('/ToUnicode', $pdf);
    }

    #[Test]
    public function multiplePagesWithSameTrueTypeFont(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Roboto Light', FontStyle::Regular, 12.0);
        $writer->text(10.0, 20.0, 'Page 1');
        $writer->addPage();
        $writer->setFont('Roboto Light', FontStyle::Regular, 12.0);
        $writer->text(10.0, 20.0, 'Page 2');
        $pdf = $writer->getOutput();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/Subtype /Type0', $pdf);
        $this->assertSame(1, substr_count($pdf, '/Subtype /CIDFontType2'));
    }

    #[Test]
    public function mixedCoreFontAndTrueTypeOnSamePage(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Helvetica', FontStyle::Regular, 12.0);
        $writer->text(10.0, 20.0, 'Core font text');
        $writer->setFont('Roboto Light', FontStyle::Regular, 12.0);
        $writer->text(10.0, 40.0, 'TrueType text');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/Subtype /Type1', $pdf);
        $this->assertStringContainsString('/Subtype /Type0', $pdf);
    }

    #[Test]
    public function setFontWithStringStyleParameter(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Helvetica', 'B', 12.0);
        $writer->text(10.0, 20.0, 'Bold');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
    }

    #[Test]
    public function cellWithTrueTypeFontTracksCodepoints(): void
    {
        $writer = new PdfWriter(compress: false, fontResolver: $this->resolver);
        $writer->addPage();
        $writer->setFont('Roboto Light', FontStyle::Regular, 12.0);
        $writer->cell(0, 10, 'Cell text');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/Subtype /Type0', $pdf);
        $this->assertStringContainsString('/FontFile2', $pdf);
    }
}
