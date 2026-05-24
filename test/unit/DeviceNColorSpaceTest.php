<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\DeviceNColorSpace;
use Horde\Pdf\ExponentialFunction;
use Horde\Pdf\PostScriptFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeviceNColorSpace::class)]
final class DeviceNColorSpaceTest extends TestCase
{
    public function testPdfName(): void
    {
        $cs = new DeviceNColorSpace(
            ['Cyan', 'Magenta'],
            new DeviceCmyk(),
            new PostScriptFunction('pop pop 0 0 0 0', [0.0, 1.0, 0.0, 1.0], [0.0, 1.0, 0.0, 1.0, 0.0, 1.0, 0.0, 1.0]),
        );
        $this->assertSame('DeviceN', $cs->pdfName());
    }

    public function testComponentCountMatchesColorants(): void
    {
        $cs = new DeviceNColorSpace(
            ['Spot1', 'Spot2', 'Spot3'],
            new DeviceCmyk(),
            new PostScriptFunction('pop pop pop 0 0 0 0', [0.0, 1.0, 0.0, 1.0, 0.0, 1.0], [0.0, 1.0, 0.0, 1.0, 0.0, 1.0, 0.0, 1.0]),
        );
        $this->assertSame(3, $cs->componentCount());
    }

    public function testStoresColorantNames(): void
    {
        $names = ['PANTONE 300 C', 'PANTONE 485 C'];
        $cs = new DeviceNColorSpace(
            $names,
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
        $this->assertSame($names, $cs->colorantNames);
    }
}
