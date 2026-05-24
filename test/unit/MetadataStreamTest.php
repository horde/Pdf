<?php

declare(strict_types=1);

use Horde\Pdf\MetadataStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MetadataStream::class)]
class MetadataStreamTest extends TestCase
{
    public function testConstructionPreservesXml(): void
    {
        $xml = '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF/></x:xmpmeta>';
        $stream = new MetadataStream($xml);
        $this->assertSame($xml, $stream->xml);
    }

    public function testEmptyXml(): void
    {
        $stream = new MetadataStream('');
        $this->assertSame('', $stream->xml);
    }
}
