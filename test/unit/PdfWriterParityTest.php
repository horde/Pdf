<?php

declare(strict_types=1);

use Horde\Pdf\Color;
use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/PageNumberFooter.php';

#[CoversClass(PdfWriter::class)]
class PdfWriterParityTest extends TestCase
{
    private function makeWriter(): PdfWriter
    {
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w->setCompression(false);
        return $w;
    }

    private function assertValidPdf(string $pdf): void
    {
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString("%%EOF\n", $pdf);

        preg_match('/startxref\n(\d+)\n/', $pdf, $m);
        $this->assertNotEmpty($m, 'Missing startxref');
        $xrefOffset = (int) $m[1];
        $this->assertSame('xref', substr($pdf, $xrefOffset, 4));
    }

    // -----------------------------------------------------------
    // Mnemo usage pattern (note export to PDF)
    // -----------------------------------------------------------

    public function testMnemoPatternProducesValidPdf(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();

        $w->setFont('Times', 'B', 24);
        $w->multiCell(0, 24, 'Shopping List', 'B', 'L');
        $w->newLine(20);

        $w->setFont('Times', '', 14);
        $w->write(14, "- Eggs\n- Milk\n- Bread\n- Butter");

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('/BaseFont /Times-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Roman', $pdf);
        $this->assertStringContainsString('(Shopping List) Tj', $pdf);
        $this->assertStringContainsString('(- Eggs) Tj', $pdf);
        $this->assertStringContainsString('(- Bread) Tj', $pdf);
    }

    public function testMnemoLongNoteTriggersPageBreaks(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();

        $w->setFont('Times', 'B', 24);
        $w->multiCell(0, 24, 'Long Note', 'B', 'L');
        $w->newLine(20);

        $w->setFont('Times', '', 14);
        $body = str_repeat("This is a paragraph of note content that spans multiple lines. ", 200);
        $w->write(14, $body);

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertGreaterThan(1, $w->getPageNo());
        preg_match('/\/Count (\d+)/', $pdf, $m);
        $this->assertGreaterThan(1, (int) $m[1]);
    }

    // -----------------------------------------------------------
    // Jonah usage pattern (news article export to PDF)
    // -----------------------------------------------------------

    public function testJonahPatternProducesValidPdf(): void
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
        $w->multiCell(0, 24, 'Breaking: Horde Pdf Reaches Feature Parity', 'B', 'L');
        $w->newLine(20);

        $w->setFont('Times', '', 14);
        $w->write(14, 'The Horde project announced today that its modern PDF writer library has reached full feature parity with the legacy implementation.');

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('(2026-04-27) Tj', $pdf);
        $this->assertStringContainsString('(Breaking: Horde Pdf Reaches Feature Parity) Tj', $pdf);
    }

    public function testJonahMultipleArticles(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();

        for ($i = 1; $i <= 3; $i++) {
            $w->addPage();
            $w->setFont('Times', 'B', 14);
            $w->cell(0, 14, "Article $i", 0, 1);
            $w->newLine(10);

            $w->setFont('Times', '', 14);
            $w->write(14, "Body of article $i with enough text to verify rendering.");
        }

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('/Count 3', $pdf);
        $this->assertStringContainsString('(Article 1) Tj', $pdf);
        $this->assertStringContainsString('(Article 2) Tj', $pdf);
        $this->assertStringContainsString('(Article 3) Tj', $pdf);
    }

    // -----------------------------------------------------------
    // Header/footer callback fires on each addPage() and close()
    // -----------------------------------------------------------

    public function testHeaderFooterCallbackFiresOnEachPage(): void
    {
        $handler = new PageNumberFooter();
        $w = new PdfWriter(headerFooter: $handler, compress: false);
        $w->open();
        $w->addPage();
        $w->addPage();
        $w->addPage();

        $pdf = $w->getOutput();

        $this->assertSame(3, $handler->headerCallCount);
        $this->assertSame(3, $handler->footerCallCount);
    }

    public function testFooterContentInPdf(): void
    {
        $handler = new PageNumberFooter();
        $w = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
        $w = new PdfWriter(
            options: new Horde\Pdf\WriterOptions(
                unit: Horde\Pdf\Unit::Point,
                format: Horde\Pdf\PageFormat::Letter,
            ),
            headerFooter: $handler,
            compress: false,
        );
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Content', 0, 1);
        $w->addPage();

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('(Page 1) Tj', $pdf);
        $this->assertStringContainsString('(Page 2) Tj', $pdf);
    }

    // -----------------------------------------------------------
    // {nb} alias replaced with total page count
    // -----------------------------------------------------------

    public function testNbAliasReplacedAllPages(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->aliasNbPages();
        $w->open();

        for ($i = 1; $i <= 5; $i++) {
            $w->addPage();
            $w->setFont('Times', '', 14);
            $w->cell(0, 14, "Page $i of {nb}", 0, 1);
        }

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringNotContainsString('{nb}', $pdf);
        $this->assertStringContainsString('(Page 1 of 5) Tj', $pdf);
        $this->assertStringContainsString('(Page 5 of 5) Tj', $pdf);
    }

    public function testCustomNbAlias(): void
    {
        $w = $this->makeWriter();
        $w->aliasNbPages('{total}');
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Total pages: {total}', 0, 1);
        $w->addPage();
        $w->addPage();

        $pdf = $w->getOutput();

        $this->assertStringNotContainsString('{total}', $pdf);
        $this->assertStringContainsString('(Total pages: 3) Tj', $pdf);
    }

    // -----------------------------------------------------------
    // Auto page break triggers new page
    // -----------------------------------------------------------

    public function testAutoPageBreakWithMultiCell(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $text = str_repeat("Line of text that fills the page and wraps around. ", 500);
        $w->multiCell(0, 14, $text);

        $this->assertGreaterThan(1, $w->getPageNo());
    }

    public function testAutoPageBreakWithWriteMethod(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(true, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $text = str_repeat("Flowing text content. ", 200);
        $w->write(14, $text);

        $this->assertGreaterThan(1, $w->getPageNo());
    }

    public function testNoAutoPageBreakWhenDisabled(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->setAutoPageBreak(false);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        for ($i = 0; $i < 60; $i++) {
            $w->cell(0, 14, "Line $i", 0, 1);
        }

        $this->assertSame(1, $w->getPageNo());
    }

    // -----------------------------------------------------------
    // Multi-page document with mixed fonts/colors
    // -----------------------------------------------------------

    public function testMixedFontsAndColors(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();

        $w->setFont('Helvetica', 'B', 18);
        $w->setTextColor(Color::rgb(0.0, 0.0, 1.0));
        $w->cell(0, 18, 'Blue Helvetica Bold Title', 0, 1);

        $w->setFont('Times', '', 12);
        $w->setTextColor(Color::gray(0.0));
        $w->cell(0, 12, 'Black Times body text', 0, 1);

        $w->setFont('Courier', 'B', 10);
        $w->setTextColor(Color::rgb(1.0, 0.0, 0.0));
        $w->cell(0, 10, 'Red Courier Bold code', 0, 1);

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Roman', $pdf);
        $this->assertStringContainsString('/BaseFont /Courier-Bold', $pdf);
        $this->assertStringContainsString('(Blue Helvetica Bold Title) Tj', $pdf);
        $this->assertStringContainsString('(Black Times body text) Tj', $pdf);
        $this->assertStringContainsString('(Red Courier Bold code) Tj', $pdf);
        $this->assertStringContainsString('0.000 0.000 1.000 rg', $pdf);
        $this->assertStringContainsString('1.000 0.000 0.000 rg', $pdf);
    }

    // -----------------------------------------------------------
    // Cell text positioning (operator-level assertions)
    // -----------------------------------------------------------

    public function testCellTextBaselinePosition(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(200, 20, 'Baseline Test', 0, 1);

        $pdf = $w->getOutput();

        preg_match('/(\d+\.\d+) (\d+\.\d+) Td \(Baseline Test\)/', $pdf, $m);
        $this->assertNotEmpty($m, 'Could not find text positioning operators');

        $textX = (float) $m[1];
        $textY = (float) $m[2];

        $expectedY = 792.0 - (50.0 + 0.5 * 20.0 + 0.3 * 14.0);
        $this->assertEqualsWithDelta($expectedY, $textY, 0.01);
        $this->assertGreaterThan(50.0, $textX);
    }

    public function testCellCenterAlignPositioning(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Courier', '', 12);
        $w->cell(300, 14, 'Centered', 0, 1, 'C');

        $pdf = $w->getOutput();

        $strWidth = $w->getStringWidth('Centered');

        preg_match('/(\d+\.\d+) \d+\.\d+ Td \(Centered\)/', $pdf, $m);
        $this->assertNotEmpty($m);
        $textX = (float) $m[1];

        $expectedX = 50.0 + (300.0 - $strWidth) / 2;
        $this->assertEqualsWithDelta($expectedX, $textX, 0.5);
    }

    public function testCellRightAlignPositioning(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Courier', '', 12);
        $cellMargin = (28.35 / 10.0);
        $w->cell(300, 14, 'Right', 0, 1, 'R');

        $pdf = $w->getOutput();

        $strWidth = $w->getStringWidth('Right');

        preg_match('/(\d+\.\d+) \d+\.\d+ Td \(Right\)/', $pdf, $m);
        $this->assertNotEmpty($m);
        $textX = (float) $m[1];

        $expectedX = 50.0 + 300.0 - $cellMargin - $strWidth;
        $this->assertEqualsWithDelta($expectedX, $textX, 0.5);
    }

    // -----------------------------------------------------------
    // PNG image embedding
    // -----------------------------------------------------------

    public function testPngImageEmbedded(): void
    {
        $pngFile = __DIR__ . '/fixtures/horde-power1.png';
        if (!file_exists($pngFile)) {
            $this->markTestSkipped('PNG fixture not available');
        }

        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->image($pngFile, 50, 50, 100, 0);

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('/Type /XObject', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }

    // -----------------------------------------------------------
    // Fill and border combinations
    // -----------------------------------------------------------

    public function testTableLikeLayout(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Helvetica', 'B', 12);

        $w->setFillColor(Color::rgb(0.8, 0.8, 0.8));
        $w->cell(150, 14, 'Name', 1, 0, 'C', true);
        $w->cell(100, 14, 'Value', 1, 1, 'C', true);

        $w->setFont('Helvetica', '', 12);
        $w->setFillColor(Color::gray(1.0));
        $w->cell(150, 14, 'Width', 1, 0, 'L');
        $w->cell(100, 14, '612 pt', 1, 1, 'R');
        $w->cell(150, 14, 'Height', 1, 0, 'L');
        $w->cell(100, 14, '792 pt', 1, 1, 'R');

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('(Name) Tj', $pdf);
        $this->assertStringContainsString('(Value) Tj', $pdf);
        $this->assertStringContainsString('(Width) Tj', $pdf);
        $this->assertStringContainsString('(612 pt) Tj', $pdf);
        $this->assertStringContainsString('re B', $pdf);
    }

    // -----------------------------------------------------------
    // Document info via PdfWriter
    // -----------------------------------------------------------

    public function testDocumentInfoViaWriter(): void
    {
        $w = $this->makeWriter();
        $w->setInfo('Title', 'Test Document');
        $w->setInfo('Author', 'Horde Tests');
        $w->setInfo('Subject', 'Integration Testing');
        $w->open();
        $w->addPage();

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('/Title (Test Document)', $pdf);
        $this->assertStringContainsString('/Author (Horde Tests)', $pdf);
        $this->assertStringContainsString('/Subject (Integration Testing)', $pdf);
    }

    // -----------------------------------------------------------
    // Display mode via PdfWriter
    // -----------------------------------------------------------

    public function testDisplayModeViaWriter(): void
    {
        $w = $this->makeWriter();
        $w->setDisplayMode('fullwidth', 'single');
        $w->open();
        $w->addPage();

        $pdf = $w->getOutput();

        $this->assertValidPdf($pdf);
        $this->assertStringContainsString('/FitH null', $pdf);
        $this->assertStringContainsString('/PageLayout /SinglePage', $pdf);
    }

    // -----------------------------------------------------------
    // Special characters in all text methods
    // -----------------------------------------------------------

    public function testSpecialCharsInAllTextMethods(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);

        $w->cell(0, 14, 'Cell: (parens) and \\back', 0, 1);
        $w->multiCell(0, 14, 'Multi: (parens) too');
        $w->write(14, 'Write: (parens) also');

        $pdf = $w->getOutput();

        $this->assertStringContainsString('(Cell: \\(parens\\) and \\\\back) Tj', $pdf);
        $this->assertStringContainsString('(Multi: \\(parens\\) too) Tj', $pdf);
        $this->assertStringContainsString('(Write: \\(parens\\) also) Tj', $pdf);
    }

    // -----------------------------------------------------------
    // Xref table validity in writer output
    // -----------------------------------------------------------

    public function testXrefOffsetsAreValid(): void
    {
        $w = $this->makeWriter();
        $w->setMargins(50, 50);
        $w->open();
        $w->addPage();
        $w->setFont('Times', '', 14);
        $w->cell(0, 14, 'Xref test', 0, 1);
        $w->addPage();
        $w->cell(0, 14, 'Page 2', 0, 1);

        $pdf = $w->getOutput();

        preg_match('/xref\n0 (\d+)\n(.*?)\ntrailer/s', $pdf, $xrefMatch);
        $this->assertNotEmpty($xrefMatch, 'Could not find xref section');

        $lines = explode("\n", trim($xrefMatch[2]));
        foreach ($lines as $idx => $line) {
            if ($idx === 0) {
                $this->assertStringContainsString('65535 f', $line);
                continue;
            }

            preg_match('/^(\d{10}) 00000 n/', $line, $entryMatch);
            $this->assertNotEmpty($entryMatch, "Invalid xref entry at index $idx: $line");

            $offset = (int) $entryMatch[1];
            $objHeader = substr($pdf, $offset, 20);
            $this->assertMatchesRegularExpression(
                '/^\d+ 0 obj/',
                $objHeader,
                "Xref offset $offset for object $idx does not point to a valid object",
            );
        }
    }
}
