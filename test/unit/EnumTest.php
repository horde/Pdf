<?php

declare(strict_types=1);

use Horde\Pdf\CellNextPosition;
use Horde\Pdf\ColorModel;
use Horde\Pdf\DocumentState;
use Horde\Pdf\FontEncoding;
use Horde\Pdf\FontStyle;
use Horde\Pdf\LayoutMode;
use Horde\Pdf\LineCap;
use Horde\Pdf\Orientation;
use Horde\Pdf\PageFormat;
use Horde\Pdf\PdfVersion;
use Horde\Pdf\ShapeStyle;
use Horde\Pdf\TextAlign;
use Horde\Pdf\Unit;
use Horde\Pdf\ZoomMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Orientation::class)]
#[CoversClass(Unit::class)]
#[CoversClass(PageFormat::class)]
#[CoversClass(ColorModel::class)]
#[CoversClass(ShapeStyle::class)]
#[CoversClass(ZoomMode::class)]
#[CoversClass(LayoutMode::class)]
#[CoversClass(DocumentState::class)]
#[CoversClass(TextAlign::class)]
#[CoversClass(CellNextPosition::class)]
#[CoversClass(PdfVersion::class)]
#[CoversClass(FontStyle::class)]
#[CoversClass(LineCap::class)]
#[CoversClass(FontEncoding::class)]
class EnumTest extends TestCase
{
    public function testOrientationValues(): void
    {
        $this->assertSame('P', Orientation::Portrait->value);
        $this->assertSame('L', Orientation::Landscape->value);
        $this->assertCount(2, Orientation::cases());
    }

    public function testUnitScaleFactors(): void
    {
        $this->assertSame(1.0, Unit::Point->scaleFactor());
        $this->assertEqualsWithDelta(72.0 / 25.4, Unit::Millimeter->scaleFactor(), 0.0001);
        $this->assertEqualsWithDelta(72.0 / 2.54, Unit::Centimeter->scaleFactor(), 0.0001);
        $this->assertSame(72.0, Unit::Inch->scaleFactor());
    }

    public function testUnitFromString(): void
    {
        $this->assertSame(Unit::Point, Unit::from('pt'));
        $this->assertSame(Unit::Millimeter, Unit::from('mm'));
        $this->assertSame(Unit::Centimeter, Unit::from('cm'));
        $this->assertSame(Unit::Inch, Unit::from('in'));
    }

    public function testPageFormatDimensions(): void
    {
        [$w, $h] = PageFormat::A4->dimensions();
        $this->assertSame(595.28, $w);
        $this->assertSame(841.89, $h);

        [$w, $h] = PageFormat::A3->dimensions();
        $this->assertSame(841.89, $w);
        $this->assertSame(1190.55, $h);

        [$w, $h] = PageFormat::Letter->dimensions();
        $this->assertSame(612.0, $w);
        $this->assertSame(792.0, $h);
    }

    public function testPageFormatCount(): void
    {
        $this->assertCount(5, PageFormat::cases());
    }

    public function testColorModelValues(): void
    {
        $this->assertSame('rgb', ColorModel::Rgb->value);
        $this->assertSame('cmyk', ColorModel::Cmyk->value);
        $this->assertSame('gray', ColorModel::Gray->value);
        $this->assertSame('hex', ColorModel::Hex->value);
    }

    public function testShapeStylePdfOperators(): void
    {
        $this->assertSame('S', ShapeStyle::Draw->pdfOperator());
        $this->assertSame('f', ShapeStyle::Fill->pdfOperator());
        $this->assertSame('B', ShapeStyle::DrawAndFill->pdfOperator());
    }

    public function testZoomModeValues(): void
    {
        $this->assertSame('fullpage', ZoomMode::FullPage->value);
        $this->assertSame('fullwidth', ZoomMode::FullWidth->value);
        $this->assertSame('real', ZoomMode::Real->value);
        $this->assertSame('default', ZoomMode::DefaultMode->value);
    }

    public function testLayoutModeValues(): void
    {
        $this->assertSame('single', LayoutMode::Single->value);
        $this->assertSame('continuous', LayoutMode::Continuous->value);
        $this->assertSame('two', LayoutMode::Two->value);
        $this->assertSame('default', LayoutMode::DefaultMode->value);
    }

    public function testDocumentStateValues(): void
    {
        $this->assertSame(0, DocumentState::Initial->value);
        $this->assertSame(1, DocumentState::Open->value);
        $this->assertSame(2, DocumentState::PageOpen->value);
        $this->assertSame(3, DocumentState::Closed->value);
    }

    public function testTextAlignValues(): void
    {
        $this->assertSame('L', TextAlign::Left->value);
        $this->assertSame('C', TextAlign::Center->value);
        $this->assertSame('R', TextAlign::Right->value);
        $this->assertSame('J', TextAlign::Justify->value);
    }

    public function testCellNextPositionValues(): void
    {
        $this->assertSame(0, CellNextPosition::ToRight->value);
        $this->assertSame(1, CellNextPosition::NextLine->value);
        $this->assertSame(2, CellNextPosition::Below->value);
    }

    public function testPdfVersionHeader(): void
    {
        $this->assertSame('%PDF-1.4', PdfVersion::V1_4->header());
        $this->assertSame('%PDF-1.7', PdfVersion::V1_7->header());
        $this->assertSame('%PDF-2.0', PdfVersion::V2_0->header());
        $this->assertCount(5, PdfVersion::cases());
    }

    public function testFontStyleValues(): void
    {
        $this->assertSame('', FontStyle::Regular->value);
        $this->assertSame('B', FontStyle::Bold->value);
        $this->assertSame('I', FontStyle::Italic->value);
        $this->assertSame('BI', FontStyle::BoldItalic->value);
    }

    public function testLineCapValues(): void
    {
        $this->assertSame(0, LineCap::Butt->value);
        $this->assertSame(1, LineCap::Round->value);
        $this->assertSame(2, LineCap::Square->value);
    }

    public function testFontEncodingValues(): void
    {
        $this->assertSame('WinAnsiEncoding', FontEncoding::WinAnsi->value);
        $this->assertSame('MacRomanEncoding', FontEncoding::MacRoman->value);
        $this->assertSame('Symbol', FontEncoding::Symbol->value);
        $this->assertSame('ZapfDingbats', FontEncoding::ZapfDingbats->value);
    }
}
