<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\ContentStream;
use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\DeviceRgb;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\ExponentialFunction;
use Horde\Pdf\Page;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use Horde\Pdf\SeparationColorSpace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfSerializer::class)]
#[CoversClass(SeparationColorSpace::class)]
#[CoversClass(ExponentialFunction::class)]
final class SeparationSerializerTest extends TestCase
{
    private function createCatalog(SeparationColorSpace $cs): DocumentCatalog
    {
        $catalog = new DocumentCatalog();
        $resources = new ResourceDictionary();
        $resources->addColorSpace('CS1', $cs);
        $content = new ContentStream('/CS1 cs 0.750 sc', $resources);
        $page = new Page(Rectangle::fromDimensions(595.28, 841.89));
        $page->addContentStream($content);
        $catalog->addPage($page);
        return $catalog;
    }

    public function testColorSpaceInResourceDict(): void
    {
        $cs = new SeparationColorSpace(
            'SpotBlue',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertStringContainsString('/ColorSpace <<', $pdf);
        $this->assertMatchesRegularExpression('#/CS1 \d+ 0 R#', $pdf);
    }

    public function testSeparationArrayObject(): void
    {
        $cs = new SeparationColorSpace(
            'SpotBlue',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertMatchesRegularExpression('#\[/Separation /SpotBlue /DeviceCMYK \d+ 0 R\]#', $pdf);
    }

    public function testExponentialFunctionObject(): void
    {
        $cs = new SeparationColorSpace(
            'SpotGreen',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [0.5, 0.0, 1.0, 0.2]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertStringContainsString('/FunctionType 2', $pdf);
        $this->assertStringContainsString('/Domain [0.0 1.0]', $pdf);
        $this->assertStringContainsString('/C0 [0.0000 0.0000 0.0000 0.0000]', $pdf);
        $this->assertStringContainsString('/C1 [0.5000 0.0000 1.0000 0.2000]', $pdf);
        $this->assertStringContainsString('/N 1.0', $pdf);
    }

    public function testAlternateSpaceRgb(): void
    {
        $cs = new SeparationColorSpace(
            'Highlight',
            new DeviceRgb(),
            new ExponentialFunction([1.0, 1.0, 1.0], [1.0, 0.0, 0.0]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertMatchesRegularExpression('#\[/Separation /Highlight /DeviceRGB \d+ 0 R\]#', $pdf);
    }

    public function testCustomExponent(): void
    {
        $cs = new SeparationColorSpace(
            'Spot',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0], 2.2),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertStringContainsString('/N 2.2', $pdf);
    }
}
