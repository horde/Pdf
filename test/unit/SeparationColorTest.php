<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\ExponentialFunction;
use Horde\Pdf\SeparationColor;
use Horde\Pdf\SeparationColorSpace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeparationColor::class)]
final class SeparationColorTest extends TestCase
{
    private SeparationColorSpace $cs;

    protected function setUp(): void
    {
        $this->cs = new SeparationColorSpace(
            'SpotBlue',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
    }

    public function testToPdfFillString(): void
    {
        $color = new SeparationColor($this->cs, 0.75);
        $this->assertSame('/CS1 cs 0.750 sc', $color->toPdfFillString('CS1'));
    }

    public function testToPdfStrokeString(): void
    {
        $color = new SeparationColor($this->cs, 0.25);
        $this->assertSame('/CS2 CS 0.250 SC', $color->toPdfStrokeString('CS2'));
    }

    public function testFullTint(): void
    {
        $color = new SeparationColor($this->cs, 1.0);
        $this->assertSame('/CS1 cs 1.000 sc', $color->toPdfFillString('CS1'));
    }

    public function testColorSpaceAccessor(): void
    {
        $color = new SeparationColor($this->cs, 0.5);
        $this->assertSame($this->cs, $color->colorSpace());
    }
}
