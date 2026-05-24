<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\ContentStream;
use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\DeviceNColorSpace;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\ExponentialFunction;
use Horde\Pdf\Page;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\PostScriptFunction;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfSerializer::class)]
#[CoversClass(DeviceNColorSpace::class)]
#[CoversClass(PostScriptFunction::class)]
final class DeviceNSerializerTest extends TestCase
{
    private function createCatalog(DeviceNColorSpace $cs): DocumentCatalog
    {
        $catalog = new DocumentCatalog();
        $resources = new ResourceDictionary();
        $resources->addColorSpace('CS1', $cs);
        $content = new ContentStream('/CS1 cs 0.500 0.800 sc', $resources);
        $page = new Page(Rectangle::fromDimensions(595.28, 841.89));
        $page->addContentStream($content);
        $catalog->addPage($page);
        return $catalog;
    }

    public function testDeviceNArrayObject(): void
    {
        $cs = new DeviceNColorSpace(
            ['Spot1', 'Spot2'],
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.5, 0.0]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertMatchesRegularExpression('#\[/DeviceN \[/Spot1 /Spot2\] /DeviceCMYK \d+ 0 R\]#', $pdf);
    }

    public function testColorSpaceInResourceDict(): void
    {
        $cs = new DeviceNColorSpace(
            ['Spot1', 'Spot2'],
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.5, 0.0]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertStringContainsString('/ColorSpace <<', $pdf);
    }

    public function testPostScriptFunction(): void
    {
        $cs = new DeviceNColorSpace(
            ['Cyan', 'Magenta'],
            new DeviceCmyk(),
            new PostScriptFunction(
                'exch 0 0',
                [0.0, 1.0, 0.0, 1.0],
                [0.0, 1.0, 0.0, 1.0, 0.0, 1.0, 0.0, 1.0],
            ),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertStringContainsString('/FunctionType 4', $pdf);
        $this->assertStringContainsString('/Domain [0.0 1.0 0.0 1.0]', $pdf);
        $this->assertStringContainsString('/Range [0.0 1.0 0.0 1.0 0.0 1.0 0.0 1.0]', $pdf);
        $this->assertStringContainsString('{ exch 0 0 }', $pdf);
    }

    public function testThreeColorants(): void
    {
        $cs = new DeviceNColorSpace(
            ['Red', 'Green', 'Blue'],
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 1.0, 1.0, 0.0]),
        );
        $pdf = (new PdfSerializer(compress: false))->serialize($this->createCatalog($cs));

        $this->assertMatchesRegularExpression('#\[/DeviceN \[/Red /Green /Blue\] /DeviceCMYK \d+ 0 R\]#', $pdf);
    }
}
