<?php

declare(strict_types=1);

use Horde\Pdf\Border;
use Horde\Pdf\CellNextPosition;
use Horde\Pdf\Color;
use Horde\Pdf\Orientation;
use Horde\Pdf\PdfWriter;
use Horde\Pdf\TextAlign;
use Horde\Pdf\Unit;
use Horde\Pdf\WriterOptions;
use Horde\Pdf\PageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class PdfWriterTest extends TestCase
{
    public function testConstructorDefaults(): void
    {
        $w = new PdfWriter();
        $w->open();
        $w->addPage();

        $this->assertSame(1, $w->getPageNo());
    }

    public function testFromLegacyFactory(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setCompression(false);
        $w->open();
        $w->addPage();

        $pdf = $w->getOutput();
        $this->assertStringContainsString('[0.00 0.00 612.00 792.00]', $pdf);
    }

    private function makeWriter(): PdfWriter
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setCompression(false);
        return $w;
    }

    public function testSetMargins(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();

        $this->assertEqualsWithDelta(50.0, $w->getX(), 0.01);
        $this->assertEqualsWithDelta(50.0, $w->getY(), 0.01);
        $this->assertEqualsWithDelta(612.0 - 50.0 - 50.0, $w->getPageWidth(), 0.01);
    }

    public function testSetAutoPageBreak(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();

        $this->assertEqualsWithDelta(792.0 - 50.0 - 50.0, $w->getPageHeight(), 0.01);
    }

    public function testAddPageCreatesNewPage(): void
    {
        $w = new PdfWriter();
        $w->open();
        $w->addPage();
        $w->addPage();
        $w->addPage();

        $this->assertSame(3, $w->getPageNo());
    }

    public function testSetFontResolvesCoreFonts(): void
    {
        $w = $this->makeWriter();
        $w->open();
        $w->addPage();
        $w->setFont('Times', 'B', 24);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/BaseFont /Times-Bold', $pdf);
    }

    public function testSetFontArialAlias(): void
    {
        $w = $this->makeWriter();
        $w->open();
        $w->addPage();
        $w->setFont('Arial', '', 12);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
    }

    public function testCellBasicText(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Hello World', 0, 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Hello World) Tj', $pdf);
    }

    public function testCellFullWidth(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $initialX = $w->getX();
        $w->cell(0, 14, 'Full width', 0, 1);

        $this->assertEqualsWithDelta(50.0, $w->getX(), 0.01);
        $this->assertEqualsWithDelta(50.0 + 14.0, $w->getY(), 0.01);
    }

    public function testCellCursorMovementToRight(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->cell(100, 14, 'Cell 1', 0, 0);
        $this->assertEqualsWithDelta(150.0, $w->getX(), 0.01);
        $this->assertEqualsWithDelta(50.0, $w->getY(), 0.01);
    }

    public function testCellCursorMovementNextLine(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->cell(100, 14, 'Cell 1', 0, 1);
        $this->assertEqualsWithDelta(50.0, $w->getX(), 0.01);
        $this->assertEqualsWithDelta(64.0, $w->getY(), 0.01);
    }

    public function testCellCursorMovementBelow(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $startX = $w->getX();
        $w->cell(100, 14, 'Cell 1', 0, 2);
        $this->assertEqualsWithDelta($startX, $w->getX(), 0.01);
        $this->assertEqualsWithDelta(64.0, $w->getY(), 0.01);
    }

    public function testCellWithBorderFull(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(100, 14, 'Bordered', 1, 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('re S', $pdf);
    }

    public function testCellWithBorderString(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(100, 14, 'Bottom border', 'B', 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('l S', $pdf);
    }

    public function testMultiCellWrapsText(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $longText = str_repeat('This is a long sentence that should wrap around. ', 10);
        $w->multiCell(0, 14, $longText);

        $pdf = $w->getOutput();
        $tjCount = substr_count($pdf, 'Tj');
        $this->assertGreaterThan(1, $tjCount);
    }

    public function testMultiCellExplicitNewlines(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->multiCell(0, 14, "Line 1\nLine 2\nLine 3");

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Line 1) Tj', $pdf);
        $this->assertStringContainsString('(Line 2) Tj', $pdf);
        $this->assertStringContainsString('(Line 3) Tj', $pdf);
    }

    public function testWriteFlowingText(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->write(14, 'Short text');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Short text) Tj', $pdf);
    }

    public function testWriteWithLineBreaks(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->write(14, "Line A\nLine B");

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Line A) Tj', $pdf);
        $this->assertStringContainsString('(Line B) Tj', $pdf);
    }

    public function testNewLineDefault(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->cell(0, 20, 'Cell', 0, 1);
        $yAfterCell = $w->getY();
        $w->newLine();

        $this->assertEqualsWithDelta($yAfterCell + 20.0, $w->getY(), 0.01);
    }

    public function testNewLineExplicit(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();

        $yBefore = $w->getY();
        $w->newLine(30);

        $this->assertEqualsWithDelta($yBefore + 30.0, $w->getY(), 0.01);
        $this->assertEqualsWithDelta(50.0, $w->getX(), 0.01);
    }

    public function testAutoPageBreak(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->setY(790.0 - 50.0 - 1.0);
        $w->cell(0, 20, 'This triggers page break', 0, 1);

        $this->assertSame(2, $w->getPageNo());
    }

    public function testCoordinateConversion(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(100, 14, 'Test', 0, 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('Td (Test) Tj', $pdf);
        $this->assertStringContainsString('[0.00 0.00 612.00 792.00]', $pdf);
    }

    public function testUnitConversionMm(): void
    {
        $w = new PdfWriter(new WriterOptions(unit: Unit::Millimeter, format: PageFormat::A4));
        $w->setCompression(false);
        $w->open();
        $w->addPage();

        $pdf = $w->getOutput();
        $this->assertStringContainsString('[0.00 0.00 595.28 841.89]', $pdf);
    }

    public function testGetOutputProducesValidPdf(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Hello', 0, 1);

        $pdf = $w->getOutput();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString("%%EOF\n", $pdf);

        preg_match('/startxref\n(\d+)\n/', $pdf, $m);
        $this->assertNotEmpty($m);
        $xrefOffset = (int) $m[1];
        $this->assertSame('xref', substr($pdf, $xrefOffset, 4));
    }

    public function testPageNumberAlias(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->aliasNbPages();
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Page 1 of {nb}', 0, 1);
        $w->addPage();

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Page 1 of 2) Tj', $pdf);
        $this->assertStringNotContainsString('{nb}', $pdf);
    }

    public function testMnemoUsagePattern(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();

        $w->setFont('Times', 'B', 24);
        $w->multiCell(0, 24, 'My Note Title', 'B', 'L');
        $w->newLine(20);

        $w->setFont('Times', '', 14);
        $w->write(14, 'This is the note body text that can be quite long.');

        $pdf = $w->getOutput();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString("%%EOF\n", $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Roman', $pdf);
        $this->assertStringContainsString('(My Note Title) Tj', $pdf);
    }

    public function testJonahUsagePattern(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();

        $w->setFont('Times', 'B', 14);
        $w->cell(0, 14, '2026-04-27', 0, 1);
        $w->newLine(10);

        $w->setFont('Times', 'B', 24);
        $w->multiCell(0, 24, 'News Article Title', 'B', 'L');
        $w->newLine(20);

        $w->setFont('Times', '', 14);
        $w->write(14, 'This is the story body text.');

        $pdf = $w->getOutput();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('(2026-04-27) Tj', $pdf);
        $this->assertStringContainsString('(News Article Title) Tj', $pdf);
    }

    public function testGetStringWidth(): void
    {
        $w = $this->makeWriter();
        $w->open();
        $w->addPage();
        $w->setFont('Courier', '', 12);

        $width = $w->getStringWidth('Hello');
        $this->assertGreaterThan(0, $width);
        $this->assertEqualsWithDelta(600 * 5 * 12.0 / 1000.0, $width, 0.01);
    }

    public function testSetTextColor(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->setTextColor(Color::rgb(1.0, 0.0, 0.0));
        $w->cell(0, 14, 'Red text', 0, 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('1.000 0.000 0.000 rg', $pdf);
        $this->assertStringContainsString('q', $pdf);
        $this->assertStringContainsString('Q', $pdf);
    }

    public function testLandscapeOrientation(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt', 'orientation' => 'L']);
        $w->setCompression(false);
        $w->open();
        $w->addPage();

        $pdf = $w->getOutput();
        $this->assertStringContainsString('[0.00 0.00 792.00 612.00]', $pdf);
    }

    public function testMultiplePages(): void
    {
        $w = $this->makeWriter();
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Page 1 content', 0, 1);
        $w->addPage();
        $w->cell(0, 14, 'Page 2 content', 0, 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/Count 2', $pdf);
        $this->assertStringContainsString('(Page 1 content) Tj', $pdf);
        $this->assertStringContainsString('(Page 2 content) Tj', $pdf);
    }

    public function testDocumentInfo(): void
    {
        $w = $this->makeWriter();
        $w->setInfo('Title', 'My Document');
        $w->setInfo('Author', 'Test Author');
        $w->open();
        $w->addPage();

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/Title (My Document)', $pdf);
        $this->assertStringContainsString('/Author (Test Author)', $pdf);
    }

    public function testCellWithFill(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->setFillColor(Color::rgb(0.9, 0.9, 0.9));
        $w->cell(100, 14, 'Filled', 0, 1, '', true);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('re f', $pdf);
    }

    public function testCellWithFillAndBorder(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->setFillColor(Color::rgb(0.9, 0.9, 0.9));
        $w->cell(100, 14, 'Both', 1, 1, '', true);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('re B', $pdf);
    }

    public function testCellAlignCenter(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(200, 14, 'Centered', 0, 1, 'C');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Centered) Tj', $pdf);
    }

    public function testCellAlignRight(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(200, 14, 'Right', 0, 1, 'R');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Right) Tj', $pdf);
    }

    public function testSpecialCharacterEscaping(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Price: (100) 50\\%', 0, 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Price: \\(100\\) 50\\\\%) Tj', $pdf);
    }

    public function testSetPosition(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();

        $w->setX(100);
        $this->assertEqualsWithDelta(100.0, $w->getX(), 0.01);

        $w->setY(200);
        $this->assertEqualsWithDelta(200.0, $w->getY(), 0.01);
        $this->assertEqualsWithDelta(50.0, $w->getX(), 0.01);

        $w->setXY(150, 300);
        $this->assertEqualsWithDelta(150.0, $w->getX(), 0.01);
        $this->assertEqualsWithDelta(300.0, $w->getY(), 0.01);
    }

    public function testNegativePosition(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->open();
        $w->addPage();

        $w->setX(-50);
        $this->assertEqualsWithDelta(612.0 - 50.0, $w->getX(), 0.01);

        $w->setY(-30);
        $this->assertEqualsWithDelta(792.0 - 30.0, $w->getY(), 0.01);
    }

    public function testMultiCellWithBorder(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->multiCell(0, 14, "Line 1\nLine 2\nLine 3", 1);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Line 1) Tj', $pdf);
        $this->assertStringContainsString('(Line 3) Tj', $pdf);
    }
}
