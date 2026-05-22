<?php

declare(strict_types=1);

use Horde\Pdf\PdfWriter;
use Horde\Pdf\ShapeStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class DrawingPrimitivesTest extends TestCase
{
    private function makeWriter(): PdfWriter
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $w->setCompression(false);
        return $w;
    }

    public function testLineEmitsCorrectOperators(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->line(100.0, 200.0, 300.0, 400.0);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('100.00 641.89 m 300.00 441.89 l S', $pdf);
    }

    public function testRectStroke(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->rect(50.0, 50.0, 200.0, 100.0);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('50.00 791.89 200.00 -100.00 re S', $pdf);
    }

    public function testRectFill(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->rect(50.0, 50.0, 200.0, 100.0, ShapeStyle::Fill);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('50.00 791.89 200.00 -100.00 re f', $pdf);
    }

    public function testRectDrawAndFill(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->rect(50.0, 50.0, 200.0, 100.0, ShapeStyle::DrawAndFill);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('50.00 791.89 200.00 -100.00 re B', $pdf);
    }

    public function testCircleEmitsFourBezierCurves(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->circle(300.0, 400.0, 50.0);

        $pdf = $w->getOutput();
        $this->assertSame(4, substr_count($pdf, ' c'));
        $this->assertStringContainsString(' S', $pdf);
    }

    public function testCircleFillStyle(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->circle(300.0, 400.0, 50.0, ShapeStyle::Fill);

        $pdf = $w->getOutput();
        $this->assertStringContainsString(' c f', $pdf);
    }

    public function testCircleCoordinateConversion(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->circle(100.0, 100.0, 25.0);

        $pdf = $w->getOutput();
        // Center at (100, 841.89-100) = (100, 741.89) in PDF coords, radius 25
        // Start point: (100-25, 741.89) = (75, 741.89)
        $this->assertStringContainsString('75.00 741.89 m', $pdf);
    }

    public function testLineCoordinateConversionWithPoints(): void
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $w->setCompression(false);
        $w->addPage();
        // A4 in points: 595.28 x 841.89
        // line from (0,0) top-left to (595.28, 841.89) bottom-right
        $w->line(0.0, 0.0, 595.28, 841.89);

        $pdf = $w->getOutput();
        // (0, 841.89) m (595.28, 0.00) l S
        $this->assertStringContainsString('0.00 841.89 m 595.28 0.00 l S', $pdf);
    }
}
