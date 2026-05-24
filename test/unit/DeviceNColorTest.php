<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\DeviceNColor;
use Horde\Pdf\DeviceNColorSpace;
use Horde\Pdf\PdfException;
use Horde\Pdf\PostScriptFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceNColor::class)]
final class DeviceNColorTest extends TestCase
{
    private DeviceNColorSpace $cs;

    protected function setUp(): void
    {
        $this->cs = new DeviceNColorSpace(
            ['Spot1', 'Spot2'],
            new DeviceCmyk(),
            new PostScriptFunction('pop pop 0 0 0 0', [0.0, 1.0, 0.0, 1.0], [0.0, 1.0, 0.0, 1.0, 0.0, 1.0, 0.0, 1.0]),
        );
    }

    public function testRejectsWrongTintCount(): void
    {
        $this->expectException(PdfException::class);
        new DeviceNColor($this->cs, 0.5);
    }

    public function testToPdfFillString(): void
    {
        $color = new DeviceNColor($this->cs, 0.5, 0.8);
        $this->assertSame('/CS1 cs 0.500 0.800 sc', $color->toPdfFillString('CS1'));
    }

    public function testToPdfStrokeString(): void
    {
        $color = new DeviceNColor($this->cs, 1.0, 0.0);
        $this->assertSame('/CS1 CS 1.000 0.000 SC', $color->toPdfStrokeString('CS1'));
    }

    public function testColorSpaceAccessor(): void
    {
        $color = new DeviceNColor($this->cs, 0.3, 0.7);
        $this->assertSame($this->cs, $color->colorSpace());
    }
}
