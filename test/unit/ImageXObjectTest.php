<?php

declare(strict_types=1);

use Horde\Pdf\DeviceRgb;
use Horde\Pdf\ImageXObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageXObject::class)]
class ImageXObjectTest extends TestCase
{
    public function testProperties(): void
    {
        $image = new ImageXObject(
            width: 100,
            height: 200,
            colorSpace: new DeviceRgb(),
            bitsPerComponent: 8,
            filter: 'DCTDecode',
            data: 'fake-jpeg-data',
        );
        $this->assertSame(100, $image->width);
        $this->assertSame(200, $image->height);
        $this->assertSame('DeviceRGB', $image->colorSpace->pdfName());
        $this->assertSame(8, $image->bitsPerComponent);
        $this->assertSame('DCTDecode', $image->filter);
        $this->assertSame('fake-jpeg-data', $image->data);
    }
}
