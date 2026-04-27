<?php

declare(strict_types=1);

use Horde\Pdf\ContentStream;
use Horde\Pdf\CoreFont;
use Horde\Pdf\Destination;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\DocumentInfo;
use Horde\Pdf\Page;
use Horde\Pdf\PageFormat;
use Horde\Pdf\PageTree;
use Horde\Pdf\PdfVersion;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use Horde\Pdf\ViewerPreferences;
use Horde\Pdf\ZoomMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentCatalog::class)]
#[CoversClass(PageTree::class)]
#[CoversClass(Page::class)]
#[CoversClass(ResourceDictionary::class)]
#[CoversClass(ContentStream::class)]
#[CoversClass(Destination::class)]
class DocumentCatalogTest extends TestCase
{
    public function testCatalogDefaults(): void
    {
        $catalog = new DocumentCatalog();
        $this->assertSame(PdfVersion::V1_7, $catalog->version);
        $this->assertSame(0, $catalog->pageTree()->count());
        $this->assertNull($catalog->info());
        $this->assertNull($catalog->viewerPreferences());
    }

    public function testAddPage(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $catalog->addPage($page);

        $this->assertSame(1, $catalog->pageTree()->count());
        $this->assertSame($page, $catalog->pageTree()->pages()[0]);
    }

    public function testMultiplePages(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::Letter)));
        $this->assertSame(2, $catalog->pageTree()->count());
    }

    public function testSetInfo(): void
    {
        $catalog = new DocumentCatalog();
        $info = new DocumentInfo(title: 'Test');
        $catalog->setInfo($info);
        $this->assertSame($info, $catalog->info());
    }

    public function testSetViewerPreferences(): void
    {
        $catalog = new DocumentCatalog();
        $prefs = new ViewerPreferences(zoomMode: ZoomMode::FullWidth);
        $catalog->setViewerPreferences($prefs);
        $this->assertSame($prefs, $catalog->viewerPreferences());
    }

    public function testPageMediaBox(): void
    {
        $rect = Rectangle::fromPageFormat(PageFormat::A4);
        $page = new Page($rect);
        $this->assertSame($rect, $page->mediaBox);
        $this->assertSame(595.28, $page->mediaBox->width());
        $this->assertSame(841.89, $page->mediaBox->height());
    }

    public function testPageContentStreams(): void
    {
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $this->assertEmpty($page->contentStreams());

        $resources = new ResourceDictionary();
        $stream = new ContentStream('BT /F1 12 Tf ET', $resources);
        $page->addContentStream($stream);

        $this->assertCount(1, $page->contentStreams());
        $this->assertSame('BT /F1 12 Tf ET', $page->contentStreams()[0]->operators);
    }

    public function testPageResourcesMergeFromContentStreams(): void
    {
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $font = CoreFont::Helvetica->toFont();

        $resources = new ResourceDictionary();
        $resources->addFont('F1', $font);
        $stream = new ContentStream('BT /F1 12 Tf ET', $resources);
        $page->addContentStream($stream);

        $this->assertArrayHasKey('F1', $page->resourceDictionary()->fonts());
    }

    public function testResourceDictionaryMerge(): void
    {
        $rd1 = new ResourceDictionary();
        $rd1->addFont('F1', CoreFont::Helvetica->toFont());

        $rd2 = new ResourceDictionary();
        $rd2->addFont('F2', CoreFont::CourierBold->toFont());

        $rd1->merge($rd2);

        $this->assertCount(2, $rd1->fonts());
        $this->assertArrayHasKey('F1', $rd1->fonts());
        $this->assertArrayHasKey('F2', $rd1->fonts());
    }

    public function testResourceDictionaryMergeDoesNotOverwrite(): void
    {
        $helvetica = CoreFont::Helvetica->toFont();
        $courier = CoreFont::Courier->toFont();

        $rd1 = new ResourceDictionary();
        $rd1->addFont('F1', $helvetica);

        $rd2 = new ResourceDictionary();
        $rd2->addFont('F1', $courier);

        $rd1->merge($rd2);

        $this->assertSame('Helvetica', $rd1->fonts()['F1']->pdfName());
    }

    public function testResourceDictionaryIsEmpty(): void
    {
        $rd = new ResourceDictionary();
        $this->assertTrue($rd->isEmpty());

        $rd->addFont('F1', CoreFont::Helvetica->toFont());
        $this->assertFalse($rd->isEmpty());
    }

    public function testPageTreeCountAndPages(): void
    {
        $tree = new PageTree();
        $this->assertSame(0, $tree->count());

        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $tree->addPage($page);

        $this->assertSame(1, $tree->count());
        $this->assertSame([$page], $tree->pages());
    }

    public function testDestination(): void
    {
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $dest = new Destination($page, top: 700.0, left: 0.0);

        $this->assertSame($page, $dest->page);
        $this->assertSame(700.0, $dest->top);
        $this->assertSame(0.0, $dest->left);
        $this->assertNull($dest->zoom);
    }
}
