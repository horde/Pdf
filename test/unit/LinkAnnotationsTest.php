<?php

declare(strict_types=1);

use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class LinkAnnotationsTest extends TestCase
{
    private function makeWriter(): PdfWriter
    {
        $w = PdfWriter::fromLegacy(['format' => 'A4', 'unit' => 'pt']);
        $w->setCompression(false);
        return $w;
    }

    public function testUriLinkProducesAnnotInOutput(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->link(72.0, 700.0, 100.0, 20.0, 'https://www.horde.org/');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/Subtype /Link', $pdf);
        $this->assertStringContainsString('/S /URI', $pdf);
        $this->assertStringContainsString('(https://www.horde.org/)', $pdf);
    }

    public function testInternalLinkProducesDestination(): void
    {
        $w = $this->makeWriter();

        $linkId = $w->addLink();
        $w->addPage();
        $w->link(72.0, 100.0, 100.0, 20.0, $linkId);

        $w->addPage();
        $w->setLink($linkId, 200.0);

        $pdf = $w->getOutput();
        $this->assertStringContainsString('/Subtype /Link', $pdf);
        $this->assertStringContainsString('/Dest [', $pdf);
        $this->assertStringContainsString('/XYZ', $pdf);
    }

    public function testAddLinkReturnsIncrementingIds(): void
    {
        $w = $this->makeWriter();
        $id1 = $w->addLink();
        $id2 = $w->addLink();

        $this->assertSame(1, $id1);
        $this->assertSame(2, $id2);
    }

    public function testUriLinkRectangleCoordinates(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        // Place link at (100, 100) with 50x10 in points, A4 height = 841.89
        $w->link(100.0, 100.0, 50.0, 10.0, 'https://example.com');

        $pdf = $w->getOutput();
        // Rect should be [100, 841.89-100, 150, 841.89-110] = [100, 741.89, 150, 731.89]
        $this->assertStringContainsString('/Rect [100.00 741.89 150.00 731.89]', $pdf);
    }

    public function testMultipleLinksOnSamePage(): void
    {
        $w = $this->makeWriter();
        $w->addPage();
        $w->link(10.0, 10.0, 50.0, 10.0, 'https://one.example.com');
        $w->link(10.0, 30.0, 50.0, 10.0, 'https://two.example.com');

        $pdf = $w->getOutput();
        $this->assertStringContainsString('(https://one.example.com)', $pdf);
        $this->assertStringContainsString('(https://two.example.com)', $pdf);
    }

    public function testInternalLinkPointsToCorrectPage(): void
    {
        $w = $this->makeWriter();

        $linkId = $w->addLink();
        $w->addPage();
        $w->link(72.0, 100.0, 100.0, 20.0, $linkId);

        $w->addPage();
        $w->setLink($linkId, 50.0);

        $pdf = $w->getOutput();
        // The destination should reference the second page object
        // and have top = 841.89 - 50 = 791.89
        $this->assertMatchesRegularExpression('/\/Dest \[\d+ 0 R \/XYZ 0\.00 791\.89 null\]/', $pdf);
    }
}
