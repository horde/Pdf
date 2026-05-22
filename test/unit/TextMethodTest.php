<?php

declare(strict_types=1);

use Horde\Pdf\Color;
use Horde\Pdf\PdfException;
use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class TextMethodTest extends TestCase
{
    private function makeWriter(): PdfWriter
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $w->setCompression(false);
        return $w;
    }

    public function testTextEmitsCorrectOperators(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 12);
        $w->text(100.0, 200.0, 'Hello World');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('BT /F', $pdf);
        $this->assertStringContainsString('12.00 Tf', $pdf);
        $this->assertStringContainsString('100.00 641.89 Td', $pdf);
        $this->assertStringContainsString('(Hello World) Tj ET', $pdf);
    }

    public function testTextDoesNotMoveCursor(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 12);

        $xBefore = $w->getX();
        $yBefore = $w->getY();
        $w->text(300.0, 400.0, 'Test');

        $this->assertSame($xBefore, $w->getX());
        $this->assertSame($yBefore, $w->getY());
    }

    public function testTextThrowsWithoutFont(): void
    {
        $w = $this->makeWriter();
        $w->addPage();

        $this->expectException(PdfException::class);
        $w->text(100.0, 200.0, 'No font');
    }

    public function testTextCoordinateConversion(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 10);
        // A4 height in pt = 841.89, text at (0, 0) should yield (0, 841.89)
        $w->text(0.0, 0.0, 'Origin');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('0.00 841.89 Td', $pdf);
    }

    public function testTextEscapesSpecialCharacters(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 12);
        $w->text(50.0, 50.0, 'Test (with) parens\\');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(Test \\(with\\) parens\\\\)', $pdf);
    }

    public function testTextWithDifferentColor(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->setFont('Helvetica', '', 12);
        $w->setFillColor(Color::gray(1.0));
        $w->setTextColor(Color::gray(0.5));
        $w->text(50.0, 50.0, 'Gray text');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('q 0.500 g BT', $pdf);
        $this->assertStringContainsString('Tj ET Q', $pdf);
    }
}
