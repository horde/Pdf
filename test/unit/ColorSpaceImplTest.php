<?php

declare(strict_types=1);

use Horde\Pdf\ColorSpace;
use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\DeviceGray;
use Horde\Pdf\DeviceRgb;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceRgb::class)]
#[CoversClass(DeviceCmyk::class)]
#[CoversClass(DeviceGray::class)]
class ColorSpaceImplTest extends TestCase
{
    public function testDeviceRgb(): void
    {
        $cs = new DeviceRgb();
        $this->assertInstanceOf(ColorSpace::class, $cs);
        $this->assertSame('DeviceRGB', $cs->pdfName());
        $this->assertSame(3, $cs->componentCount());
    }

    public function testDeviceCmyk(): void
    {
        $cs = new DeviceCmyk();
        $this->assertInstanceOf(ColorSpace::class, $cs);
        $this->assertSame('DeviceCMYK', $cs->pdfName());
        $this->assertSame(4, $cs->componentCount());
    }

    public function testDeviceGray(): void
    {
        $cs = new DeviceGray();
        $this->assertInstanceOf(ColorSpace::class, $cs);
        $this->assertSame('DeviceGray', $cs->pdfName());
        $this->assertSame(1, $cs->componentCount());
    }
}
