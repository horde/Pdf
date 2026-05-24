<?php

declare(strict_types=1);

use Horde\Pdf\MetadataStream;
use Horde\Pdf\XmpBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(XmpBuilder::class)]
#[CoversClass(MetadataStream::class)]
class XmpBuilderTest extends TestCase
{
    public function testBasicStructure(): void
    {
        $builder = new XmpBuilder(title: 'Test Document');
        $stream = $builder->build();

        $this->assertStringContainsString('<?xpacket begin=', $stream->xml);
        $this->assertStringContainsString('<?xpacket end="w"?>', $stream->xml);
        $this->assertStringContainsString('x:xmpmeta', $stream->xml);
        $this->assertStringContainsString('rdf:RDF', $stream->xml);
    }

    public function testDublinCoreTitle(): void
    {
        $builder = new XmpBuilder(title: 'My PDF Title');
        $stream = $builder->build();

        $this->assertStringContainsString('dc:title', $stream->xml);
        $this->assertStringContainsString('rdf:Alt', $stream->xml);
        $this->assertStringContainsString('x-default', $stream->xml);
        $this->assertStringContainsString('My PDF Title', $stream->xml);
    }

    public function testDublinCoreCreator(): void
    {
        $builder = new XmpBuilder(creator: 'John Doe');
        $stream = $builder->build();

        $this->assertStringContainsString('dc:creator', $stream->xml);
        $this->assertStringContainsString('rdf:Seq', $stream->xml);
        $this->assertStringContainsString('John Doe', $stream->xml);
    }

    public function testXmpBasicDates(): void
    {
        $date = new DateTimeImmutable('2026-05-24T10:30:00+02:00');
        $builder = new XmpBuilder(
            creatorTool: 'Horde PDF',
            createDate: $date,
            modifyDate: $date,
        );
        $stream = $builder->build();

        $this->assertStringContainsString('xmp:CreatorTool', $stream->xml);
        $this->assertStringContainsString('Horde PDF', $stream->xml);
        $this->assertStringContainsString('xmp:CreateDate', $stream->xml);
        $this->assertStringContainsString('2026-05-24T10:30:00+02:00', $stream->xml);
        $this->assertStringContainsString('xmp:ModifyDate', $stream->xml);
    }

    public function testPdfProperties(): void
    {
        $builder = new XmpBuilder(
            producer: 'Horde PDF Library',
            keywords: 'test pdf metadata',
        );
        $stream = $builder->build();

        $this->assertStringContainsString('pdf:Producer', $stream->xml);
        $this->assertStringContainsString('Horde PDF Library', $stream->xml);
        $this->assertStringContainsString('pdf:Keywords', $stream->xml);
        $this->assertStringContainsString('test pdf metadata', $stream->xml);
    }

    public function testPdfaIdentification(): void
    {
        $builder = new XmpBuilder(pdfaPart: 1, pdfaConformance: 'B');
        $stream = $builder->build();

        $this->assertStringContainsString('pdfaid:part', $stream->xml);
        $this->assertStringContainsString('>1<', $stream->xml);
        $this->assertStringContainsString('pdfaid:conformance', $stream->xml);
        $this->assertStringContainsString('>B<', $stream->xml);
    }

    public function testDocumentId(): void
    {
        $builder = new XmpBuilder(documentId: 'uuid:12345678-1234-1234-1234-123456789012');
        $stream = $builder->build();

        $this->assertStringContainsString('xmpMM:DocumentID', $stream->xml);
        $this->assertStringContainsString('uuid:12345678-1234-1234-1234-123456789012', $stream->xml);
    }

    public function testXmlIsWellFormed(): void
    {
        $builder = new XmpBuilder(
            title: 'Test',
            creator: 'Author',
            creatorTool: 'Tool',
            createDate: new DateTimeImmutable(),
            producer: 'Horde',
            pdfaPart: 2,
            pdfaConformance: 'A',
            documentId: 'uuid:test',
        );
        $stream = $builder->build();

        $innerXml = $this->extractXmlFromXpacket($stream->xml);
        $doc = new DOMDocument();
        $result = $doc->loadXML($innerXml);
        $this->assertTrue($result, 'XMP XML should be well-formed');
    }

    public function testPaddingPresent(): void
    {
        $builder = new XmpBuilder(title: 'Padded');
        $stream = $builder->build();

        $packetEnd = strpos($stream->xml, '<?xpacket end=');
        $rdfEnd = strrpos($stream->xml, '</x:xmpmeta>');
        $padding = substr($stream->xml, $rdfEnd + strlen("</x:xmpmeta>\n"), $packetEnd - $rdfEnd - strlen("</x:xmpmeta>\n"));
        $this->assertGreaterThan(100, strlen($padding));
    }

    private function extractXmlFromXpacket(string $xmp): string
    {
        $start = strpos($xmp, '<x:xmpmeta');
        $end = strpos($xmp, '</x:xmpmeta>') + strlen('</x:xmpmeta>');
        return substr($xmp, $start, $end - $start);
    }
}
