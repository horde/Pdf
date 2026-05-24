<?php

declare(strict_types=1);

use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\MetadataStream;
use Horde\Pdf\OutputIntent;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
#[CoversClass(PdfSerializer::class)]
#[CoversClass(MetadataStream::class)]
#[CoversClass(OutputIntent::class)]
#[CoversClass(DocumentCatalog::class)]
class XmpOutputTest extends TestCase
{
    public function testMetadataStreamInPdf(): void
    {
        $xmp = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"/></x:xmpmeta>';
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Hello');
        $pdf->setMetadata(new MetadataStream($xmp));

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Type /Metadata', $output);
        $this->assertStringContainsString('/Subtype /XML', $output);
        $this->assertStringContainsString('/Metadata ', $output);
        $this->assertStringContainsString($xmp, $output);
    }

    public function testOutputIntentInPdf(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Hello');
        $pdf->addOutputIntent(new OutputIntent());

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Type /OutputIntent', $output);
        $this->assertStringContainsString('/S /GTS_PDFA1', $output);
        $this->assertStringContainsString('/OutputConditionIdentifier (sRGB)', $output);
        $this->assertStringContainsString('/RegistryName (http://www.color.org)', $output);
        $this->assertStringContainsString('/OutputIntents [', $output);
    }

    public function testMetadataStreamNotCompressed(): void
    {
        $xmp = '<?xpacket begin="' . "\xEF\xBB\xBF" . '"?><x:xmpmeta xmlns:x="adobe:ns:meta/"/><?xpacket end="w"?>';
        $pdf = new PdfWriter();
        $pdf->setCompression(true);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Content');
        $pdf->setMetadata(new MetadataStream($xmp));

        $output = $pdf->getOutput();

        $pos = strpos($output, '/Type /Metadata');
        $this->assertNotFalse($pos);
        $header = substr($output, $pos - 10, 80);
        $this->assertStringNotContainsString('/FlateDecode', $header);
    }

    public function testNoMetadataNoEntry(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Plain');

        $output = $pdf->getOutput();

        $this->assertStringNotContainsString('/Type /Metadata', $output);
        $this->assertStringNotContainsString('/OutputIntents', $output);
    }

    public function testMultipleOutputIntents(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Hello');
        $pdf->addOutputIntent(new OutputIntent());
        $pdf->addOutputIntent(new OutputIntent(subtype: 'GTS_PDFX', outputConditionIdentifier: 'FOGRA39'));

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/S /GTS_PDFA1', $output);
        $this->assertStringContainsString('/S /GTS_PDFX', $output);
        $this->assertStringContainsString('/OutputConditionIdentifier (FOGRA39)', $output);
    }
}
