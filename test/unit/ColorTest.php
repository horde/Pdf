<?php

declare(strict_types=1);

use Horde\Pdf\Color;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Color::class)]
class ColorTest extends TestCase
{
    public function testRgbFillString(): void
    {
        $color = Color::rgb(1.0, 0.0, 0.0);
        $this->assertSame('1.000 0.000 0.000 rg', $color->toPdfFillString());
    }

    public function testRgbStrokeString(): void
    {
        $color = Color::rgb(0.0, 0.5, 1.0);
        $this->assertSame('0.000 0.500 1.000 RG', $color->toPdfStrokeString());
    }

    public function testCmykFillString(): void
    {
        $color = Color::cmyk(1.0, 0.0, 0.0, 0.5);
        $this->assertSame('1.000 0.000 0.000 0.500 k', $color->toPdfFillString());
    }

    public function testCmykStrokeString(): void
    {
        $color = Color::cmyk(0.0, 1.0, 0.0, 0.0);
        $this->assertSame('0.000 1.000 0.000 0.000 K', $color->toPdfStrokeString());
    }

    public function testGrayFillString(): void
    {
        $color = Color::gray(0.5);
        $this->assertSame('0.500 g', $color->toPdfFillString());
    }

    public function testGrayStrokeString(): void
    {
        $color = Color::gray(0.0);
        $this->assertSame('0.000 G', $color->toPdfStrokeString());
    }

    public function testHexFullForm(): void
    {
        $color = Color::hex('#FF0000');
        $this->assertSame('1.000 0.000 0.000 rg', $color->toPdfFillString());
    }

    public function testHexShortForm(): void
    {
        $color = Color::hex('#F00');
        $this->assertSame('1.000 0.000 0.000 rg', $color->toPdfFillString());
    }

    public function testHexWithoutHash(): void
    {
        $color = Color::hex('00FF00');
        $this->assertSame('0.000 1.000 0.000 rg', $color->toPdfFillString());
    }

    public function testHexMatchesLegacyWriter(): void
    {
        $color = Color::hex('#F00');
        $this->assertSame('1.000 0.000 0.000 RG', $color->toPdfStrokeString());

        $color = Color::hex('#0F0');
        $this->assertSame('0.000 1.000 0.000 rg', $color->toPdfFillString());

        $color = Color::hex('#00F');
        $this->assertSame('0.000 0.000 1.000 rg', $color->toPdfFillString());
    }
}
