<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\IccBasedColorSpace;
use Horde\Pdf\IccColor;
use Horde\Pdf\IccProfile;
use Horde\Pdf\PdfException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IccColor::class)]
final class IccColorTest extends TestCase
{
    private IccBasedColorSpace $rgbSpace;

    protected function setUp(): void
    {
        $this->rgbSpace = new IccBasedColorSpace(new IccProfile('data', 3));
    }

    public function testRejectsWrongComponentCount(): void
    {
        $this->expectException(PdfException::class);
        new IccColor($this->rgbSpace, 0.5, 0.3);
    }

    public function testColorSpaceAccessor(): void
    {
        $color = new IccColor($this->rgbSpace, 0.1, 0.2, 0.3);
        $this->assertSame($this->rgbSpace, $color->colorSpace());
    }

    public function testToPdfFillString(): void
    {
        $color = new IccColor($this->rgbSpace, 0.5, 0.3, 0.2);
        $this->assertSame('/CS1 cs 0.500 0.300 0.200 sc', $color->toPdfFillString('CS1'));
    }

    public function testToPdfStrokeString(): void
    {
        $color = new IccColor($this->rgbSpace, 0.5, 0.3, 0.2);
        $this->assertSame('/CS1 CS 0.500 0.300 0.200 SC', $color->toPdfStrokeString('CS1'));
    }

    public function testGrayColor(): void
    {
        $graySpace = new IccBasedColorSpace(new IccProfile('data', 1));
        $color = new IccColor($graySpace, 0.75);
        $this->assertSame('/CS2 cs 0.750 sc', $color->toPdfFillString('CS2'));
    }

    public function testCmykColor(): void
    {
        $cmykSpace = new IccBasedColorSpace(new IccProfile('data', 4));
        $color = new IccColor($cmykSpace, 1.0, 0.0, 0.5, 0.25);
        $this->assertSame('/CS3 cs 1.000 0.000 0.500 0.250 sc', $color->toPdfFillString('CS3'));
    }
}
