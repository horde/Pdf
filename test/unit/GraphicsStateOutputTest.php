<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\BlendMode;
use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
final class GraphicsStateOutputTest extends TestCase
{
    #[Test]
    public function setAlphaEmitsGsOperator(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.5);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertMatchesRegularExpression('/\/GS\d+ gs/', $pdf);
    }

    #[Test]
    public function setAlphaProducesExtGStateInResource(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.5);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/ExtGState <<', $pdf);
    }

    #[Test]
    public function setAlphaProducesExtGStateObject(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.5);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/Type /ExtGState', $pdf);
        $this->assertStringContainsString('/ca 0.500', $pdf);
        $this->assertStringContainsString('/CA 0.500', $pdf);
    }

    #[Test]
    public function setBlendModeProducesBlendModeEntry(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setBlendMode(BlendMode::Multiply);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/BM /Multiply', $pdf);
    }

    #[Test]
    public function sameAlphaOnSamePageReusesSameGState(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.5);
        $writer->text(10.0, 20.0, 'Hello');
        $writer->setAlpha(0.5);
        $writer->text(10.0, 40.0, 'World');
        $pdf = $writer->getOutput();

        $this->assertSame(1, substr_count($pdf, '/Type /ExtGState'));
    }

    #[Test]
    public function differentAlphaProducesDifferentGStates(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.3);
        $writer->text(10.0, 20.0, 'Hello');
        $writer->setAlpha(0.7);
        $writer->text(10.0, 40.0, 'World');
        $pdf = $writer->getOutput();

        $this->assertSame(2, substr_count($pdf, '/Type /ExtGState'));
    }

    #[Test]
    public function separateStrokeAlpha(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.3, 0.9);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('/ca 0.300', $pdf);
        $this->assertStringContainsString('/CA 0.900', $pdf);
    }

    #[Test]
    public function outputIsValidPdfWithExtGState(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->setAlpha(0.5);
        $writer->setBlendMode(BlendMode::Screen);
        $writer->text(10.0, 20.0, 'Hello');
        $pdf = $writer->getOutput();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
        $this->assertStringContainsString('xref', $pdf);
    }
}
