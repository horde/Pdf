<?php

declare(strict_types=1);

use Horde\Pdf\ContentStream;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\Page;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use Horde\Pdf\StructureElement;
use Horde\Pdf\StructureTree;
use Horde\Pdf\StructureType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfSerializer::class)]
class TaggedPdfSerializerTest extends TestCase
{
    private function buildTaggedCatalog(): DocumentCatalog
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromDimensions(612, 792));
        $page->addContentStream(new ContentStream('/P <</MCID 0>> BDC (Hello) Tj EMC', new ResourceDictionary()));
        $catalog->addPage($page);

        $tree = new StructureTree();
        $doc = new StructureElement(StructureType::Document);
        $p = new StructureElement(StructureType::P);
        $p->addMarkedContent(0, $page);
        $doc->addChild($p);
        $tree->add($doc);
        $catalog->setStructureTree($tree);

        return $catalog;
    }

    public function testOutputContainsMarkInfo(): void
    {
        $output = (new PdfSerializer(compress: false))->serialize($this->buildTaggedCatalog());

        $this->assertStringContainsString('/MarkInfo', $output);
        $this->assertStringContainsString('/Marked true', $output);
    }

    public function testOutputContainsStructTreeRoot(): void
    {
        $output = (new PdfSerializer(compress: false))->serialize($this->buildTaggedCatalog());

        $this->assertStringContainsString('/StructTreeRoot', $output);
        $this->assertStringContainsString('/Type /StructTreeRoot', $output);
    }

    public function testOutputContainsStructElem(): void
    {
        $output = (new PdfSerializer(compress: false))->serialize($this->buildTaggedCatalog());

        $this->assertStringContainsString('/Type /StructElem', $output);
        $this->assertStringContainsString('/S /P', $output);
        $this->assertStringContainsString('/S /Document', $output);
    }

    public function testOutputContainsStructParents(): void
    {
        $output = (new PdfSerializer(compress: false))->serialize($this->buildTaggedCatalog());

        $this->assertStringContainsString('/StructParents 0', $output);
    }

    public function testOutputContainsParentTree(): void
    {
        $output = (new PdfSerializer(compress: false))->serialize($this->buildTaggedCatalog());

        $this->assertStringContainsString('/Nums [', $output);
    }

    public function testNestedStructureElements(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromDimensions(612, 792));
        $page->addContentStream(new ContentStream('/P <</MCID 0>> BDC (text) Tj EMC', new ResourceDictionary()));
        $catalog->addPage($page);

        $tree = new StructureTree();
        $doc = new StructureElement(StructureType::Document);
        $sect = new StructureElement(StructureType::Sect);
        $p = new StructureElement(StructureType::P);
        $p->addMarkedContent(0, $page);
        $sect->addChild($p);
        $doc->addChild($sect);
        $tree->add($doc);
        $catalog->setStructureTree($tree);

        $output = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/S /Sect', $output);
        $this->assertStringContainsString('/S /P', $output);
        $this->assertStringContainsString('/P ', $output);
    }

    public function testAltTextSerialized(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromDimensions(612, 792));
        $page->addContentStream(new ContentStream('/Figure <</MCID 0>> BDC (img) Tj EMC', new ResourceDictionary()));
        $catalog->addPage($page);

        $tree = new StructureTree();
        $doc = new StructureElement(StructureType::Document);
        $fig = new StructureElement(StructureType::Figure, altText: 'A photo');
        $fig->addMarkedContent(0, $page);
        $doc->addChild($fig);
        $tree->add($doc);
        $catalog->setStructureTree($tree);

        $output = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Alt (A photo)', $output);
    }
}
