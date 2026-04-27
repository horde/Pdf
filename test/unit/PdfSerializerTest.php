<?php

declare(strict_types=1);

use Horde\Pdf\Color;
use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\CoreFont;
use Horde\Pdf\Destination;
use Horde\Pdf\DeviceRgb;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\DocumentInfo;
use Horde\Pdf\GoToAction;
use Horde\Pdf\ImageXObject;
use Horde\Pdf\LayoutMode;
use Horde\Pdf\LinkAnnotation;
use Horde\Pdf\Page;
use Horde\Pdf\PageFormat;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\PdfVersion;
use Horde\Pdf\Rectangle;
use Horde\Pdf\UriAction;
use Horde\Pdf\ViewerPreferences;
use Horde\Pdf\ZoomMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfSerializer::class)]
class PdfSerializerTest extends TestCase
{
    public function testMinimalDocument(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringStartsWith('%PDF-1.7', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('/Type /Page', $pdf);
        $this->assertStringContainsString('/Type /Pages', $pdf);
        $this->assertStringContainsString('/Type /Catalog', $pdf);
        $this->assertStringContainsString('xref', $pdf);
        $this->assertStringContainsString('trailer', $pdf);
        $this->assertStringContainsString('startxref', $pdf);
    }

    public function testPdfVersionHeader(): void
    {
        $catalog = new DocumentCatalog(version: PdfVersion::V1_4);
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
    }

    public function testSinglePageWithText(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $font = CoreFont::Helvetica->toFont();
        $builder = new ContentStreamBuilder();
        $stream = $builder
            ->beginText()
            ->setFont($font, 12.0)
            ->moveTextPosition(72.0, 720.0)
            ->showText('Hello, World!')
            ->endText()
            ->build();

        $page->addContentStream($stream);
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Type /Font', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
        $this->assertStringContainsString('/Encoding /WinAnsiEncoding', $pdf);
        $this->assertStringContainsString('BT', $pdf);
        $this->assertStringContainsString('(Hello, World!) Tj', $pdf);
        $this->assertStringContainsString('ET', $pdf);
    }

    public function testMultiPage(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::Letter)));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Count 2', $pdf);
        $this->assertSame(2, substr_count($pdf, '/Type /Page' . "\n"));

        preg_match('/\/Kids \[(.+?)\]/', $pdf, $matches);
        $this->assertNotEmpty($matches);
        $kids = trim($matches[1]);
        $refs = preg_split('/\s+/', $kids);
        $this->assertCount(6, $refs);
    }

    public function testDocumentInfo(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setInfo(new DocumentInfo(
            title: 'Test PDF',
            author: 'Horde Test',
            creationDate: 'D:20260427120000',
        ));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Title (Test PDF)', $pdf);
        $this->assertStringContainsString('/Author (Horde Test)', $pdf);
        $this->assertStringContainsString('/CreationDate (D:20260427120000)', $pdf);
        $this->assertStringContainsString('/Producer (Horde PDF)', $pdf);
    }

    public function testSymbolFontNoEncoding(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $font = CoreFont::Symbol->toFont();
        $builder = new ContentStreamBuilder();
        $stream = $builder
            ->beginText()
            ->setFont($font, 12.0)
            ->showText('abc')
            ->endText()
            ->build();

        $page->addContentStream($stream);
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/BaseFont /Symbol', $pdf);
        $this->assertStringNotContainsString('/Encoding /WinAnsiEncoding', $pdf);
    }

    public function testCompression(): void
    {
        if (!function_exists('gzcompress')) {
            $this->markTestSkipped('zlib not available');
        }

        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $font = CoreFont::Courier->toFont();
        $builder = new ContentStreamBuilder();
        $stream = $builder
            ->beginText()
            ->setFont($font, 12.0)
            ->showText('Compressed content test string that should be long enough')
            ->endText()
            ->build();

        $page->addContentStream($stream);
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: true))->serialize($catalog);

        $this->assertStringContainsString('/Filter /FlateDecode', $pdf);
    }

    public function testUncompressed(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringNotContainsString('/Filter /FlateDecode', $pdf);
    }

    public function testImageSerialization(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $image = new ImageXObject(
            width: 100,
            height: 80,
            colorSpace: new DeviceRgb(),
            bitsPerComponent: 8,
            filter: 'DCTDecode',
            data: 'fake-jpeg-data-for-test',
        );

        $builder = new ContentStreamBuilder();
        $stream = $builder
            ->drawImage($image, 72.0, 650.0, 200.0, 160.0)
            ->build();

        $page->addContentStream($stream);
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Type /XObject', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertStringContainsString('/Width 100', $pdf);
        $this->assertStringContainsString('/Height 80', $pdf);
        $this->assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
        $this->assertStringContainsString('/Filter /DCTDecode', $pdf);
        $this->assertStringContainsString('/BitsPerComponent 8', $pdf);
    }

    public function testUriLinkAnnotation(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $link = new LinkAnnotation($rect, new UriAction('https://www.horde.org/'));
        $page->addAnnotation($link);

        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Annots [', $pdf);
        $this->assertStringContainsString('/Subtype /Link', $pdf);
        $this->assertStringContainsString('/S /URI', $pdf);
        $this->assertStringContainsString('/URI (https://www.horde.org/)', $pdf);
    }

    public function testInternalLinkAnnotation(): void
    {
        $catalog = new DocumentCatalog();

        $page1 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $page2 = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $dest = new Destination($page2, top: 841.89);
        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $link = new LinkAnnotation($rect, new GoToAction($dest));
        $page1->addAnnotation($link);

        $catalog->addPage($page1);
        $catalog->addPage($page2);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Annots [', $pdf);
        $this->assertStringContainsString('/Dest [', $pdf);
        $this->assertStringContainsString('/XYZ', $pdf);
    }

    public function testViewerPreferencesFullWidth(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setViewerPreferences(new ViewerPreferences(
            zoomMode: ZoomMode::FullWidth,
            layoutMode: LayoutMode::Single,
        ));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/OpenAction [', $pdf);
        $this->assertStringContainsString('/FitH null', $pdf);
        $this->assertStringContainsString('/PageLayout /SinglePage', $pdf);
    }

    public function testXrefTableFormat(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        preg_match('/xref\n0 (\d+)\n/', $pdf, $matches);
        $this->assertNotEmpty($matches);
        $count = (int) $matches[1];
        $this->assertGreaterThan(1, $count);

        $this->assertMatchesRegularExpression('/0000000000 65535 f /', $pdf);
        $this->assertMatchesRegularExpression('/\d{10} 00000 n /', $pdf);
    }

    public function testXrefOffsetsPointToObjects(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 12.0)
            ->showText('Test')
            ->endText()
            ->build();
        $page->addContentStream($stream);
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        preg_match('/xref\n0 (\d+)\n(.*?)\ntrailer/s', $pdf, $xrefMatch);
        $this->assertNotEmpty($xrefMatch, 'Could not find xref section');

        $lines = explode("\n", trim($xrefMatch[2]));
        foreach ($lines as $idx => $line) {
            if ($idx === 0) {
                $this->assertStringContainsString('65535 f', $line);
                continue;
            }

            preg_match('/^(\d{10}) 00000 n/', $line, $entryMatch);
            $this->assertNotEmpty($entryMatch, "Invalid xref entry at index $idx: $line");

            $offset = (int) $entryMatch[1];
            $objHeader = substr($pdf, $offset, 20);
            $this->assertMatchesRegularExpression(
                '/^\d+ 0 obj/',
                $objHeader,
                "Xref offset $offset for object $idx does not point to a valid object: " . substr($pdf, $offset, 40),
            );
        }
    }

    public function testStringEscaping(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setInfo(new DocumentInfo(
            title: 'Test (with parens) and \\backslash',
        ));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Title (Test \\(with parens\\) and \\\\backslash)', $pdf);
    }

    public function testMediaBoxFormat(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('[0.00 0.00 595.28 841.89]', $pdf);
    }

    public function testMultipleFonts(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $helvetica = CoreFont::Helvetica->toFont();
        $courier = CoreFont::Courier->toFont();
        $builder = new ContentStreamBuilder();
        $stream = $builder
            ->beginText()
            ->setFont($helvetica, 12.0)
            ->showText('Helvetica text')
            ->setFont($courier, 10.0)
            ->showText('Courier text')
            ->endText()
            ->build();

        $page->addContentStream($stream);
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
        $this->assertStringContainsString('/BaseFont /Courier', $pdf);
        $this->assertSame(2, substr_count($pdf, '/Type /Font'));
    }

    public function testPngImageWithDecodeParmsAndTransparency(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $image = new ImageXObject(
            width: 10,
            height: 10,
            colorSpace: new DeviceRgb(),
            bitsPerComponent: 8,
            filter: 'FlateDecode',
            data: 'fake-png-data',
            decodeParms: '/DecodeParms <</Predictor 15 /Colors 3 /BitsPerComponent 8 /Columns 10>>',
            transparency: [255, 0, 128],
        );

        $builder = new ContentStreamBuilder();
        $builder->drawImage($image, 72.0, 700.0, 100.0, 100.0);
        $page->addContentStream($builder->build());
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Type /XObject', $pdf);
        $this->assertStringContainsString('/Filter /FlateDecode', $pdf);
        $this->assertStringContainsString('/Predictor 15', $pdf);
        $this->assertStringContainsString('/Mask [255 255 0 0 128 128 ]', $pdf);
    }

    public function testPngIndexedColorWithPalette(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $palette = str_repeat("\xFF\x00\x00", 1) . str_repeat("\x00\xFF\x00", 1);
        $image = new ImageXObject(
            width: 5,
            height: 5,
            colorSpace: new DeviceRgb(),
            bitsPerComponent: 8,
            filter: 'FlateDecode',
            data: 'fake-indexed-data',
            decodeParms: '/DecodeParms <</Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns 5>>',
            palette: $palette,
        );

        $builder = new ContentStreamBuilder();
        $builder->drawImage($image, 72.0, 700.0, 50.0, 50.0);
        $page->addContentStream($builder->build());
        $catalog->addPage($page);

        $pdf = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/ColorSpace [/Indexed /DeviceRGB 1', $pdf);
        $this->assertStringNotContainsString('/ColorSpace /DeviceRGB', $pdf);
    }
}
