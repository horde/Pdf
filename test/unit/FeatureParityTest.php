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
use Horde\Pdf\JpegParser;
use Horde\Pdf\LayoutMode;
use Horde\Pdf\LineCap;
use Horde\Pdf\LineDashPattern;
use Horde\Pdf\LinkAnnotation;
use Horde\Pdf\Orientation;
use Horde\Pdf\Page;
use Horde\Pdf\PageFormat;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\PdfVersion;
use Horde\Pdf\Rectangle;
use Horde\Pdf\Type1Font;
use Horde\Pdf\UriAction;
use Horde\Pdf\ViewerPreferences;
use Horde\Pdf\ZoomMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves feature parity between the new object graph and
 * legacy Horde_Pdf_Writer for all primitive capabilities.
 *
 * Each test exercises a specific Writer capability via the
 * object graph and validates the PDF output contains the
 * correct PDF operators and structure.
 */
#[CoversClass(PdfSerializer::class)]
#[CoversClass(ContentStreamBuilder::class)]
#[CoversClass(Page::class)]
#[CoversClass(DocumentCatalog::class)]
class FeatureParityTest extends TestCase
{
    private function serialize(DocumentCatalog $catalog): string
    {
        return (new PdfSerializer(compress: false))->serialize($catalog);
    }

    private function catalogWithPage(PageFormat $format = PageFormat::A4): array
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat($format));
        return [$catalog, $page];
    }

    // -------------------------------------------------------
    // Writer::__construct / page setup parity
    // -------------------------------------------------------

    public function testPageFormatA4(): void
    {
        [$catalog, $page] = $this->catalogWithPage(PageFormat::A4);
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('[0.00 0.00 595.28 841.89]', $pdf);
    }

    public function testPageFormatLetter(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::Letter));
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('[0.00 0.00 612.00 792.00]', $pdf);
    }

    public function testLandscapeOrientation(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4, Orientation::Landscape));
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('[0.00 0.00 841.89 595.28]', $pdf);
    }

    public function testMultiplePageFormats(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::Letter)));
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('[0.00 0.00 595.28 841.89]', $pdf);
        $this->assertStringContainsString('[0.00 0.00 612.00 792.00]', $pdf);
        $this->assertStringContainsString('/Count 2', $pdf);
    }

    // -------------------------------------------------------
    // Writer::setFont / font handling parity
    // All 14 core fonts must serialize correctly
    // -------------------------------------------------------

    public function testAllCoreFontsSerialize(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $builder = new ContentStreamBuilder();
        $builder->beginText();
        foreach (CoreFont::cases() as $cf) {
            $builder->setFont($cf->toFont(), 10.0);
        }
        $builder->endText();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertSame(14, substr_count($pdf, '/Type /Font'));
        $this->assertStringContainsString('/BaseFont /Courier', $pdf);
        $this->assertStringContainsString('/BaseFont /Courier-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Courier-Oblique', $pdf);
        $this->assertStringContainsString('/BaseFont /Courier-BoldOblique', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Oblique', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-BoldOblique', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Roman', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Italic', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-BoldItalic', $pdf);
        $this->assertStringContainsString('/BaseFont /Symbol', $pdf);
        $this->assertStringContainsString('/BaseFont /ZapfDingbats', $pdf);
    }

    public function testSymbolAndZapfDingbatsOmitEncoding(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $builder = new ContentStreamBuilder();
        $builder->beginText()
            ->setFont(CoreFont::Symbol->toFont(), 12.0)
            ->setFont(CoreFont::ZapfDingbats->toFont(), 12.0)
            ->endText();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $fontSections = [];
        preg_match_all('/\/BaseFont \/(\S+).*?endobj/s', $pdf, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $fontSections[$m[1]] = $m[0];
        }

        $this->assertArrayHasKey('Symbol', $fontSections);
        $this->assertStringNotContainsString('WinAnsiEncoding', $fontSections['Symbol']);

        $this->assertArrayHasKey('ZapfDingbats', $fontSections);
        $this->assertStringNotContainsString('WinAnsiEncoding', $fontSections['ZapfDingbats']);
    }

    public function testRegularFontsHaveWinAnsiEncoding(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $builder = new ContentStreamBuilder();
        $builder->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 12.0)
            ->endText();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        preg_match('/\/BaseFont \/Helvetica\n.*?endobj/s', $pdf, $m);
        $this->assertStringContainsString('/Encoding /WinAnsiEncoding', $m[0]);
    }

    // -------------------------------------------------------
    // Writer::getStringWidth parity
    // -------------------------------------------------------

    public function testStringWidthCourierUniform(): void
    {
        $font = CoreFont::Courier->toFont();
        $this->assertEqualsWithDelta(
            600 * 5 * 12.0 / 1000.0,
            $font->widthOfString('Hello', 12.0),
            0.001,
        );
    }

    public function testStringWidthHelveticaProportional(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $w = $font->widths();
        $expected = ($w['H'] + $w['i']) * 10.0 / 1000.0;
        $this->assertEqualsWithDelta($expected, $font->widthOfString('Hi', 10.0), 0.001);
    }

    // -------------------------------------------------------
    // Writer::text parity (direct coordinate text placement)
    // -------------------------------------------------------

    public function testDirectTextPlacement(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 12.0)
            ->moveTextPosition(72.0, 720.0)
            ->showText('Hello, World!')
            ->endText();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('BT', $pdf);
        $this->assertStringContainsString('/F1 12.00 Tf', $pdf);
        $this->assertStringContainsString('72.00 720.00 Td', $pdf);
        $this->assertStringContainsString('(Hello, World!) Tj', $pdf);
        $this->assertStringContainsString('ET', $pdf);
    }

    public function testMultipleFontSwitching(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 24.0)
            ->moveTextPosition(72.0, 750.0)
            ->showText('Title')
            ->setFont(CoreFont::Times->toFont(), 12.0)
            ->moveTextPosition(0.0, -30.0)
            ->showText('Body text')
            ->setFont(CoreFont::CourierBold->toFont(), 10.0)
            ->moveTextPosition(0.0, -20.0)
            ->showText('Code')
            ->endText();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('(Title) Tj', $pdf);
        $this->assertStringContainsString('(Body text) Tj', $pdf);
        $this->assertStringContainsString('(Code) Tj', $pdf);
        $this->assertSame(3, substr_count($pdf, '/Type /Font'));
    }

    // -------------------------------------------------------
    // Writer::setDrawColor / setFillColor / setTextColor parity
    // -------------------------------------------------------

    public function testRgbColors(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setFillColor(Color::rgb(1.0, 0.0, 0.0))
            ->setStrokeColor(Color::rgb(0.0, 0.0, 1.0))
            ->rect(72.0, 700.0, 100.0, 50.0)
            ->fillAndStroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('1.000 0.000 0.000 rg', $pdf);
        $this->assertStringContainsString('0.000 0.000 1.000 RG', $pdf);
    }

    public function testCmykColors(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setFillColor(Color::cmyk(1.0, 0.0, 0.0, 0.0))
            ->rect(72.0, 700.0, 100.0, 50.0)
            ->fill();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('1.000 0.000 0.000 0.000 k', $pdf);
    }

    public function testGrayColor(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setFillColor(Color::gray(0.5))
            ->rect(72.0, 700.0, 100.0, 50.0)
            ->fill();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('0.500 g', $pdf);
    }

    public function testHexColor(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setFillColor(Color::hex('#FF0000'))
            ->rect(72.0, 700.0, 100.0, 50.0)
            ->fill();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('1.000 0.000 0.000 rg', $pdf);
    }

    // -------------------------------------------------------
    // Writer::line / Writer::rect / Writer::circle parity
    // -------------------------------------------------------

    public function testLine(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->moveTo(72.0, 720.0)
            ->lineTo(523.0, 720.0)
            ->stroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('72.00 720.00 m', $pdf);
        $this->assertStringContainsString('523.00 720.00 l', $pdf);
        $this->assertStringContainsString('S', $pdf);
    }

    public function testRectangle(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setFillColor(Color::rgb(0.9, 0.9, 0.9))
            ->rect(72.0, 650.0, 451.0, 100.0)
            ->fill();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('72.00 650.00 451.00 100.00 re', $pdf);
        $this->assertStringContainsString('f', $pdf);
    }

    public function testRectangleStrokeAndFill(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setFillColor(Color::rgb(0.9, 0.9, 0.9))
            ->setStrokeColor(Color::rgb(0.0, 0.0, 0.0))
            ->rect(72.0, 650.0, 100.0, 50.0)
            ->fillAndStroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('B', $pdf);
    }

    public function testCircleViaBezierCurves(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $cx = 300.0;
        $cy = 400.0;
        $r = 50.0;
        $k = 0.5522847498;
        $builder = new ContentStreamBuilder();
        $builder
            ->moveTo($cx + $r, $cy)
            ->curveTo($cx + $r, $cy + $r * $k, $cx + $r * $k, $cy + $r, $cx, $cy + $r)
            ->curveTo($cx - $r * $k, $cy + $r, $cx - $r, $cy + $r * $k, $cx - $r, $cy)
            ->curveTo($cx - $r, $cy - $r * $k, $cx - $r * $k, $cy - $r, $cx, $cy - $r)
            ->curveTo($cx + $r * $k, $cy - $r, $cx + $r, $cy - $r * $k, $cx + $r, $cy)
            ->stroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertSame(4, substr_count($pdf, ' c'));
        $this->assertStringContainsString('350.00 400.00 m', $pdf);
    }

    // -------------------------------------------------------
    // Writer::setLineWidth parity
    // -------------------------------------------------------

    public function testLineWidth(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setLineWidth(2.0)
            ->moveTo(72.0, 720.0)
            ->lineTo(523.0, 720.0)
            ->stroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('2.00 w', $pdf);
    }

    public function testLineCap(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setLineCap(LineCap::Round)
            ->moveTo(72.0, 720.0)
            ->lineTo(200.0, 720.0)
            ->stroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('1 J', $pdf);
    }

    public function testDashPattern(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->setDashPattern(new LineDashPattern([5.0, 3.0]))
            ->moveTo(72.0, 720.0)
            ->lineTo(200.0, 720.0)
            ->stroke();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('[5.00 3.00] 0.00 d', $pdf);
    }

    // -------------------------------------------------------
    // Writer::setInfo parity
    // -------------------------------------------------------

    public function testDocumentInfoFields(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setInfo(new DocumentInfo(
            title: 'My Title',
            author: 'My Author',
            subject: 'My Subject',
            keywords: 'pdf test horde',
            creator: 'Horde',
            creationDate: 'D:20260427120000',
        ));

        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('/Producer (Horde PDF)', $pdf);
        $this->assertStringContainsString('/Title (My Title)', $pdf);
        $this->assertStringContainsString('/Author (My Author)', $pdf);
        $this->assertStringContainsString('/Subject (My Subject)', $pdf);
        $this->assertStringContainsString('/Keywords (pdf test horde)', $pdf);
        $this->assertStringContainsString('/Creator (Horde)', $pdf);
        $this->assertStringContainsString('/CreationDate (D:20260427120000)', $pdf);
    }

    // -------------------------------------------------------
    // Writer::setDisplayMode parity
    // -------------------------------------------------------

    public function testDisplayModeFullPage(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setViewerPreferences(new ViewerPreferences(
            zoomMode: ZoomMode::FullPage,
        ));

        $pdf = $this->serialize($catalog);
        $this->assertStringContainsString('/Fit]', $pdf);
    }

    public function testDisplayModeFullWidth(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setViewerPreferences(new ViewerPreferences(
            zoomMode: ZoomMode::FullWidth,
        ));

        $pdf = $this->serialize($catalog);
        $this->assertStringContainsString('/FitH null]', $pdf);
    }

    public function testDisplayModeReal(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setViewerPreferences(new ViewerPreferences(
            zoomMode: ZoomMode::Real,
        ));

        $pdf = $this->serialize($catalog);
        $this->assertStringContainsString('/XYZ null null 1]', $pdf);
    }

    public function testLayoutModeSingle(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setViewerPreferences(new ViewerPreferences(
            layoutMode: LayoutMode::Single,
        ));

        $pdf = $this->serialize($catalog);
        $this->assertStringContainsString('/PageLayout /SinglePage', $pdf);
    }

    public function testLayoutModeContinuous(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->addPage(new Page(Rectangle::fromPageFormat(PageFormat::A4)));
        $catalog->setViewerPreferences(new ViewerPreferences(
            layoutMode: LayoutMode::Continuous,
        ));

        $pdf = $this->serialize($catalog);
        $this->assertStringContainsString('/PageLayout /OneColumn', $pdf);
    }

    // -------------------------------------------------------
    // Writer::link / Writer::addLink / Writer::setLink parity
    // -------------------------------------------------------

    public function testExternalUriLink(): void
    {
        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $page->addAnnotation(new LinkAnnotation($rect, new UriAction('https://www.horde.org/')));

        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('/Annots [', $pdf);
        $this->assertStringContainsString('/Type /Annot', $pdf);
        $this->assertStringContainsString('/Subtype /Link', $pdf);
        $this->assertStringContainsString('/Border [0 0 0]', $pdf);
        $this->assertStringContainsString('/S /URI', $pdf);
        $this->assertStringContainsString('/URI (https://www.horde.org/)', $pdf);
    }

    public function testInternalGoToLink(): void
    {
        $catalog = new DocumentCatalog();
        $page1 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $page2 = new Page(Rectangle::fromPageFormat(PageFormat::A4));

        $dest = new Destination($page2, top: 841.89);
        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $page1->addAnnotation(new LinkAnnotation($rect, new GoToAction($dest)));

        $catalog->addPage($page1);
        $catalog->addPage($page2);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('/Dest [', $pdf);
        $this->assertStringContainsString('/XYZ', $pdf);
    }

    // -------------------------------------------------------
    // Writer::image parity (JPEG)
    // -------------------------------------------------------

    public function testJpegImageInPdf(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $img = imagecreatetruecolor(40, 30);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        $path = tempnam(sys_get_temp_dir(), 'horde_pdf_parity_') . '.jpg';
        imagejpeg($img, $path, 75);
        imagedestroy($img);

        try {
            $image = JpegParser::parseFile($path);

            $catalog = new DocumentCatalog();
            $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
            $builder = new ContentStreamBuilder();
            $builder->drawImage($image, 72.0, 650.0, 200.0, 150.0);

            $page->addContentStream($builder->build());
            $catalog->addPage($page);
            $pdf = $this->serialize($catalog);

            $this->assertStringContainsString('/Type /XObject', $pdf);
            $this->assertStringContainsString('/Subtype /Image', $pdf);
            $this->assertStringContainsString('/Width 40', $pdf);
            $this->assertStringContainsString('/Height 30', $pdf);
            $this->assertStringContainsString('/ColorSpace /DeviceRGB', $pdf);
            $this->assertStringContainsString('/Filter /DCTDecode', $pdf);
            $this->assertStringContainsString('/BitsPerComponent 8', $pdf);
        } finally {
            @unlink($path);
        }
    }

    // -------------------------------------------------------
    // Writer::setCompression parity
    // -------------------------------------------------------

    public function testCompressionEnabledProducesFlateStreams(): void
    {
        if (!function_exists('gzcompress')) {
            $this->markTestSkipped('zlib not available');
        }

        $catalog = new DocumentCatalog();
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $builder = new ContentStreamBuilder();
        $builder
            ->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 12.0)
            ->showText('Compressed text')
            ->endText();
        $page->addContentStream($builder->build());
        $catalog->addPage($page);

        $compressed = (new PdfSerializer(compress: true))->serialize($catalog);
        $uncompressed = (new PdfSerializer(compress: false))->serialize($catalog);

        $this->assertStringContainsString('/Filter /FlateDecode', $compressed);
        $this->assertStringNotContainsString('/Filter /FlateDecode', $uncompressed);
    }

    // -------------------------------------------------------
    // Writer::writeRotated parity (via save/restore + transform)
    // -------------------------------------------------------

    public function testGraphicsStateSaveRestore(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->save()
            ->setLineWidth(3.0)
            ->moveTo(100.0, 100.0)
            ->lineTo(200.0, 200.0)
            ->stroke()
            ->restore();

        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('q', $pdf);
        $this->assertStringContainsString('Q', $pdf);
    }

    // -------------------------------------------------------
    // Writer::getOutput / Writer::save parity
    // -------------------------------------------------------

    public function testOutputIsValidPdf(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->setInfo(new DocumentInfo(title: 'Validity Test'));
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $builder = new ContentStreamBuilder();
        $builder
            ->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 12.0)
            ->moveTextPosition(72.0, 720.0)
            ->showText('Testing PDF validity')
            ->endText();
        $page->addContentStream($builder->build());
        $catalog->addPage($page);

        $pdf = $this->serialize($catalog);

        $this->assertStringStartsWith('%PDF-1.7', $pdf);
        $this->assertStringContainsString("%%EOF\n", $pdf);

        preg_match('/startxref\n(\d+)\n/', $pdf, $m);
        $this->assertNotEmpty($m, 'Missing startxref');
        $xrefOffset = (int) $m[1];
        $this->assertSame('xref', substr($pdf, $xrefOffset, 4));

        preg_match('/\/Size (\d+)/', $pdf, $sizeMatch);
        preg_match('/xref\n0 (\d+)/', $pdf, $countMatch);
        $this->assertSame($sizeMatch[1], $countMatch[1]);
    }

    // -------------------------------------------------------
    // Writer::text with special characters parity
    // -------------------------------------------------------

    public function testSpecialCharacterEscaping(): void
    {
        [$catalog, $page] = $this->catalogWithPage();
        $builder = new ContentStreamBuilder();
        $builder
            ->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 12.0)
            ->showText('Price: $100 (discounted) 50\\% off')
            ->endText();
        $page->addContentStream($builder->build());
        $catalog->addPage($page);
        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('(Price: $100 \\(discounted\\) 50\\\\% off) Tj', $pdf);
    }

    // -------------------------------------------------------
    // Multi-page with different content per page
    // (Writer::addPage + content per page)
    // -------------------------------------------------------

    public function testMultiPageWithContent(): void
    {
        $catalog = new DocumentCatalog();

        $page1 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $b1 = new ContentStreamBuilder();
        $b1->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 24.0)
            ->moveTextPosition(72.0, 750.0)
            ->showText('Page 1')
            ->endText();
        $page1->addContentStream($b1->build());

        $page2 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $b2 = new ContentStreamBuilder();
        $b2->beginText()
            ->setFont(CoreFont::Times->toFont(), 24.0)
            ->moveTextPosition(72.0, 750.0)
            ->showText('Page 2')
            ->endText();
        $page2->addContentStream($b2->build());

        $catalog->addPage($page1);
        $catalog->addPage($page2);

        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('/Count 2', $pdf);
        $this->assertStringContainsString('(Page 1) Tj', $pdf);
        $this->assertStringContainsString('(Page 2) Tj', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
        $this->assertStringContainsString('/BaseFont /Times-Roman', $pdf);
    }

    // -------------------------------------------------------
    // Prove legacy Writer hello-world equivalent
    // -------------------------------------------------------

    /**
     * Produces the same visual result as the legacy testHelloWorldUncompressed
     * but via the object graph. Validates PDF structure, not byte-for-byte match.
     */
    public function testHelloWorldEquivalent(): void
    {
        $catalog = new DocumentCatalog();
        $catalog->setInfo(new DocumentInfo(creationDate: 'D:20071105152947'));

        $page1 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $b1 = new ContentStreamBuilder();
        $b1->beginText()
            ->setFont(CoreFont::Courier->toFont(), 40.0)
            ->moveTextPosition(10.0, 10.0)
            ->showText('Hello World')
            ->endText();
        $page1->addContentStream($b1->build());

        $page2 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $b2 = new ContentStreamBuilder();
        $b2->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 40.0)
            ->moveTextPosition(10.0, 10.0)
            ->showText('Hello World')
            ->setFont(CoreFont::HelveticaBold->toFont(), 40.0)
            ->moveTextPosition(0.0, -50.0)
            ->showText('Hello World')
            ->setFont(CoreFont::HelveticaItalic->toFont(), 40.0)
            ->moveTextPosition(0.0, -50.0)
            ->showText('Hello World')
            ->setFont(CoreFont::HelveticaBoldItalic->toFont(), 40.0)
            ->moveTextPosition(0.0, -50.0)
            ->showText('Hello World')
            ->endText();
        $page2->addContentStream($b2->build());

        $page3 = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $b3 = new ContentStreamBuilder();
        $b3->beginText()
            ->setFont(CoreFont::Helvetica->toFont(), 10.0)
            ->moveTextPosition(10.0, 10.0)
            ->showText('Hello World 10pt')
            ->setFont(CoreFont::Helvetica->toFont(), 14.0)
            ->moveTextPosition(0.0, -20.0)
            ->showText('Hello World 14pt')
            ->setFont(CoreFont::Helvetica->toFont(), 18.0)
            ->moveTextPosition(0.0, -24.0)
            ->showText('Hello World 18pt')
            ->setFont(CoreFont::Helvetica->toFont(), 22.0)
            ->moveTextPosition(0.0, -28.0)
            ->showText('Hello World 22pt')
            ->endText();
        $page3->addContentStream($b3->build());

        $catalog->addPage($page1);
        $catalog->addPage($page2);
        $catalog->addPage($page3);

        $pdf = $this->serialize($catalog);

        $this->assertStringContainsString('/Count 3', $pdf);
        $this->assertStringContainsString('/BaseFont /Courier', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Oblique', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-BoldOblique', $pdf);
        $this->assertStringContainsString('/CreationDate (D:20071105152947)', $pdf);
        $this->assertStringContainsString('(Hello World) Tj', $pdf);
    }
}
