<?php

declare(strict_types=1);

use Horde\Pdf\Orientation;
use Horde\Pdf\PageFormat;
use Horde\Pdf\Rectangle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Rectangle::class)]
class RectangleTest extends TestCase
{
    public function testConstructor(): void
    {
        $rect = new Rectangle(10.0, 20.0, 110.0, 220.0);
        $this->assertSame(10.0, $rect->llx);
        $this->assertSame(20.0, $rect->lly);
        $this->assertSame(110.0, $rect->urx);
        $this->assertSame(220.0, $rect->ury);
    }

    public function testWidth(): void
    {
        $rect = new Rectangle(10.0, 0.0, 110.0, 100.0);
        $this->assertSame(100.0, $rect->width());
    }

    public function testHeight(): void
    {
        $rect = new Rectangle(0.0, 20.0, 100.0, 220.0);
        $this->assertSame(200.0, $rect->height());
    }

    public function testFromDimensions(): void
    {
        $rect = Rectangle::fromDimensions(595.28, 841.89);
        $this->assertSame(0.0, $rect->llx);
        $this->assertSame(0.0, $rect->lly);
        $this->assertSame(595.28, $rect->urx);
        $this->assertSame(841.89, $rect->ury);
    }

    public function testFromPageFormatPortrait(): void
    {
        $rect = Rectangle::fromPageFormat(PageFormat::A4);
        $this->assertSame(595.28, $rect->width());
        $this->assertSame(841.89, $rect->height());
    }

    public function testFromPageFormatLandscape(): void
    {
        $rect = Rectangle::fromPageFormat(PageFormat::A4, Orientation::Landscape);
        $this->assertSame(841.89, $rect->width());
        $this->assertSame(595.28, $rect->height());
    }

    public function testToPdfArray(): void
    {
        $rect = new Rectangle(0.0, 0.0, 595.28, 841.89);
        $this->assertSame('[0.00 0.00 595.28 841.89]', $rect->toPdfArray());
    }
}
