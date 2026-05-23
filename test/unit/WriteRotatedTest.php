<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
final class WriteRotatedTest extends TestCase
{
    #[Test]
    public function writeRotatedEmitsCmOperator(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(100.0, 200.0, 'Rotated', 45.0);
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('cm', $pdf);
    }

    #[Test]
    public function writeRotatedWrapsInSaveRestore(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(100.0, 200.0, 'Rotated', 90.0);
        $pdf = $writer->getOutput();

        $this->assertMatchesRegularExpression('/q\s+.*cm.*Tj.*ET\s+Q/s', $pdf);
    }

    #[Test]
    public function writeRotated90DegreesMatrix(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(100.0, 200.0, 'Test', 90.0);
        $pdf = $writer->getOutput();

        $this->assertMatchesRegularExpression('/0\.0000 1\.0000 -1\.0000 0\.0000/', $pdf);
    }

    #[Test]
    public function writeRotated45DegreesMatrix(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(50.0, 100.0, 'Angled', 45.0);
        $pdf = $writer->getOutput();

        $this->assertMatchesRegularExpression('/0\.7071 0\.7071 -0\.7071 0\.7071/', $pdf);
    }

    #[Test]
    public function writeRotatedContainsTextString(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(10.0, 20.0, 'Hello', 30.0);
        $pdf = $writer->getOutput();

        $this->assertStringContainsString('(Hello)', $pdf);
    }

    #[Test]
    public function writeRotatedContainsFontReference(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(10.0, 20.0, 'Hello', 30.0);
        $pdf = $writer->getOutput();

        $this->assertMatchesRegularExpression('/\/F\d+ 12\.00 Tf/', $pdf);
    }

    #[Test]
    public function writeRotatedOutputIsValidPdf(): void
    {
        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->setFont('Helvetica', '', 12.0);
        $writer->writeRotated(50.0, 50.0, 'Valid PDF', 180.0);
        $pdf = $writer->getOutput();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }
}
