<?php

declare(strict_types=1);

use Horde\Pdf\Color;
use Horde\Pdf\Orientation;
use Horde\Pdf\PdfWriter;
use Horde\Pdf\WriterOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class ColorFontAccessorsTest extends TestCase
{
    private function makeWriter(): PdfWriter
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $w->setCompression(false);
        return $w;
    }

    public function testGetFillColorReturnsDefault(): void
    {
        $w = $this->makeWriter();
        $color = $w->getFillColor();
        $this->assertSame('1.000 g', $color->toPdfFillString());
    }

    public function testGetFillColorAfterSet(): void
    {
        $w = $this->makeWriter();
        $red = Color::rgb(1.0, 0.0, 0.0);
        $w->addPage();
        $w->setFillColor($red);
        $this->assertSame($red, $w->getFillColor());
    }

    public function testGetTextColorReturnsDefault(): void
    {
        $w = $this->makeWriter();
        $color = $w->getTextColor();
        $this->assertSame('0.000 g', $color->toPdfFillString());
    }

    public function testGetTextColorAfterSet(): void
    {
        $w = $this->makeWriter();
        $blue = Color::rgb(0.0, 0.0, 1.0);
        $w->setTextColor($blue);
        $this->assertSame($blue, $w->getTextColor());
    }

    public function testGetDrawColorReturnsDefault(): void
    {
        $w = $this->makeWriter();
        $color = $w->getDrawColor();
        $this->assertSame('0.000 G', $color->toPdfStrokeString());
    }

    public function testGetDrawColorAfterSet(): void
    {
        $w = $this->makeWriter();
        $green = Color::rgb(0.0, 1.0, 0.0);
        $w->addPage();
        $w->setDrawColor($green);
        $this->assertSame($green, $w->getDrawColor());
    }

    public function testSetFontStyleChangesBold(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 12);
        $w->setFontStyle('B');
        $w->text(50.0, 50.0, 'Bold');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/Helvetica-Bold', $pdf);
    }

    public function testSetFontStyleChangesItalic(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 12);
        $w->setFontStyle('I');
        $w->text(50.0, 50.0, 'Italic');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/Helvetica-Oblique', $pdf);
    }

    public function testSetFontStylePreservesSize(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Courier', '', 18);
        $w->setFontStyle('B');
        $w->text(50.0, 50.0, 'Big bold');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('18.00 Tf', $pdf);
    }

    public function testGetDefaultOrientation(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'orientation' => 'L']);
        $this->assertSame(Orientation::Landscape, $w->getDefaultOrientation());
    }

    public function testGetDefaultOrientationPortrait(): void
    {
        $w = $this->makeWriter();
        $this->assertSame(Orientation::Portrait, $w->getDefaultOrientation());
    }

    public function testGetFormatWidthA4(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $this->assertEqualsWithDelta(595.28, $w->getFormatWidth(), 0.01);
    }

    public function testGetFormatHeightA4(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $this->assertEqualsWithDelta(841.89, $w->getFormatHeight(), 0.01);
    }

    public function testGetFormatDimensionsInMillimeters(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'mm']);
        $this->assertEqualsWithDelta(210.0, $w->getFormatWidth(), 0.1);
        $this->assertEqualsWithDelta(297.0, $w->getFormatHeight(), 0.1);
    }

    public function testGetFormatLandscapeSwapsDimensions(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt', 'orientation' => 'L']);
        $this->assertEqualsWithDelta(841.89, $w->getFormatWidth(), 0.01);
        $this->assertEqualsWithDelta(595.28, $w->getFormatHeight(), 0.01);
    }
}
