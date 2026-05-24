<?php

declare(strict_types=1);

use Horde\Pdf\PdfException;
use Horde\Pdf\PdfWriter;
use Horde\Pdf\StructureType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class TaggedPdfWriterTest extends TestCase
{
    public function testBeginEndStructureProducesTaggedPdf(): void
    {
        $pdf = new PdfWriter(compress: false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);

        $pdf->beginStructure(StructureType::P);
        $pdf->cell(0, 10, 'Hello tagged world');
        $pdf->endStructure();

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/MarkInfo', $output);
        $this->assertStringContainsString('/Marked true', $output);
        $this->assertStringContainsString('/StructTreeRoot', $output);
        $this->assertStringContainsString('/Type /StructElem', $output);
        $this->assertStringContainsString('/S /P', $output);
        $this->assertStringContainsString('/S /Document', $output);
        $this->assertStringContainsString('/StructParents 0', $output);
        $this->assertStringContainsString('/P <</MCID 0>> BDC', $output);
        $this->assertStringContainsString('EMC', $output);
    }

    public function testNestedStructures(): void
    {
        $pdf = new PdfWriter(compress: false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);

        $pdf->beginStructure(StructureType::Sect);
        $pdf->beginStructure(StructureType::H1);
        $pdf->cell(0, 10, 'Title');
        $pdf->endStructure();
        $pdf->beginStructure(StructureType::P);
        $pdf->cell(0, 10, 'Body text');
        $pdf->endStructure();
        $pdf->endStructure();

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/S /Sect', $output);
        $this->assertStringContainsString('/S /H1', $output);
        $this->assertStringContainsString('/S /P', $output);
        $this->assertStringContainsString('/Sect <</MCID 0>> BDC', $output);
        $this->assertStringContainsString('/H1 <</MCID 1>> BDC', $output);
        $this->assertStringContainsString('/P <</MCID 2>> BDC', $output);
    }

    public function testMultiplePagesGetSequentialStructParents(): void
    {
        $pdf = new PdfWriter(compress: false);

        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->beginStructure(StructureType::P);
        $pdf->cell(0, 10, 'Page 1');
        $pdf->endStructure();

        $pdf->addPage();
        $pdf->beginStructure(StructureType::P);
        $pdf->cell(0, 10, 'Page 2');
        $pdf->endStructure();

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/StructParents 0', $output);
        $this->assertStringContainsString('/StructParents 1', $output);
    }

    public function testUnclosedStructureThrows(): void
    {
        $pdf = new PdfWriter(compress: false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->beginStructure(StructureType::P);
        $pdf->cell(0, 10, 'text');

        $this->expectException(PdfException::class);
        $pdf->getOutput();
    }

    public function testEndStructureWithoutBeginThrows(): void
    {
        $pdf = new PdfWriter(compress: false);
        $pdf->addPage();

        $this->expectException(PdfException::class);
        $pdf->endStructure();
    }
}
