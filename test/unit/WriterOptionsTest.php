<?php

declare(strict_types=1);

use Horde\Pdf\CustomPageFormat;
use Horde\Pdf\Orientation;
use Horde\Pdf\PageFormat;
use Horde\Pdf\Unit;
use Horde\Pdf\WriterOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WriterOptions::class)]
#[CoversClass(CustomPageFormat::class)]
class WriterOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $options = new WriterOptions();
        $this->assertSame(Orientation::Portrait, $options->orientation);
        $this->assertSame(Unit::Millimeter, $options->unit);
        $this->assertSame(PageFormat::A4, $options->format);
    }

    public function testCustomValues(): void
    {
        $options = new WriterOptions(
            orientation: Orientation::Landscape,
            unit: Unit::Point,
            format: PageFormat::A3,
        );
        $this->assertSame(Orientation::Landscape, $options->orientation);
        $this->assertSame(Unit::Point, $options->unit);
        $this->assertSame(PageFormat::A3, $options->format);
    }

    public function testCustomPageFormat(): void
    {
        $custom = new CustomPageFormat(100.0, 200.0);
        $options = new WriterOptions(
            unit: Unit::Point,
            format: $custom,
        );
        $this->assertInstanceOf(CustomPageFormat::class, $options->format);
        $this->assertSame(100.0, $custom->width);
        $this->assertSame(200.0, $custom->height);
    }

    public function testFormatDimensionsInPointsForStandardFormat(): void
    {
        $options = new WriterOptions(format: PageFormat::A4);
        [$w, $h] = $options->formatDimensionsInPoints();
        $this->assertSame(595.28, $w);
        $this->assertSame(841.89, $h);
    }

    public function testFormatDimensionsInPointsForCustomFormat(): void
    {
        $options = new WriterOptions(
            unit: Unit::Point,
            format: new CustomPageFormat(50.0, 50.0),
        );
        [$w, $h] = $options->formatDimensionsInPoints();
        $this->assertSame(50.0, $w);
        $this->assertSame(50.0, $h);
    }

    public function testFormatDimensionsInPointsConvertsUnits(): void
    {
        $options = new WriterOptions(
            unit: Unit::Inch,
            format: new CustomPageFormat(8.5, 11.0),
        );
        [$w, $h] = $options->formatDimensionsInPoints();
        $this->assertSame(612.0, $w);
        $this->assertSame(792.0, $h);
    }

    public function testFromLegacyDefaults(): void
    {
        $options = WriterOptions::fromLegacy();
        $this->assertSame(Orientation::Portrait, $options->orientation);
        $this->assertSame(Unit::Millimeter, $options->unit);
        $this->assertSame(PageFormat::A4, $options->format);
    }

    public function testFromLegacyWithValues(): void
    {
        $options = WriterOptions::fromLegacy([
            'orientation' => 'L',
            'unit' => 'pt',
            'format' => 'A3',
        ]);
        $this->assertSame(Orientation::Landscape, $options->orientation);
        $this->assertSame(Unit::Point, $options->unit);
        $this->assertSame(PageFormat::A3, $options->format);
    }

    public function testFromLegacyWithCustomFormat(): void
    {
        $options = WriterOptions::fromLegacy([
            'format' => [50.0, 50.0],
            'unit' => 'pt',
        ]);
        $this->assertInstanceOf(CustomPageFormat::class, $options->format);
        [$w, $h] = $options->formatDimensionsInPoints();
        $this->assertSame(50.0, $w);
        $this->assertSame(50.0, $h);
    }

    public function testFromLegacyLandscapeSpelled(): void
    {
        $options = WriterOptions::fromLegacy(['orientation' => 'Landscape']);
        $this->assertSame(Orientation::Landscape, $options->orientation);
    }
}
