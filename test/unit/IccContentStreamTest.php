<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\IccBasedColorSpace;
use Horde\Pdf\IccColor;
use Horde\Pdf\IccProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentStreamBuilder::class)]
final class IccContentStreamTest extends TestCase
{
    public function testFillColorEmitsOperators(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('data', 3));
        $color = new IccColor($cs, 0.5, 0.3, 0.2);

        $stream = (new ContentStreamBuilder())
            ->setIccFillColor($color)
            ->build();

        $this->assertStringContainsString('/CS1 cs 0.500 0.300 0.200 sc', $stream->operators);
    }

    public function testStrokeColorEmitsOperators(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('data', 3));
        $color = new IccColor($cs, 0.1, 0.9, 0.4);

        $stream = (new ContentStreamBuilder())
            ->setIccStrokeColor($color)
            ->build();

        $this->assertStringContainsString('/CS1 CS 0.100 0.900 0.400 SC', $stream->operators);
    }

    public function testSameColorSpaceReusesResourceName(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('data', 3));
        $color1 = new IccColor($cs, 0.5, 0.3, 0.2);
        $color2 = new IccColor($cs, 0.1, 0.2, 0.3);

        $stream = (new ContentStreamBuilder())
            ->setIccFillColor($color1)
            ->setIccFillColor($color2)
            ->build();

        $this->assertStringContainsString('/CS1 cs 0.500 0.300 0.200 sc', $stream->operators);
        $this->assertStringContainsString('/CS1 cs 0.100 0.200 0.300 sc', $stream->operators);

        $colorSpaces = $stream->resources->colorSpaces();
        $this->assertCount(1, $colorSpaces);
        $this->assertArrayHasKey('CS1', $colorSpaces);
    }

    public function testDifferentColorSpacesGetDifferentNames(): void
    {
        $cs1 = new IccBasedColorSpace(new IccProfile('profile1', 3));
        $cs2 = new IccBasedColorSpace(new IccProfile('profile2', 1));

        $stream = (new ContentStreamBuilder())
            ->setIccFillColor(new IccColor($cs1, 0.5, 0.3, 0.2))
            ->setIccFillColor(new IccColor($cs2, 0.8))
            ->build();

        $colorSpaces = $stream->resources->colorSpaces();
        $this->assertCount(2, $colorSpaces);
        $this->assertArrayHasKey('CS1', $colorSpaces);
        $this->assertArrayHasKey('CS2', $colorSpaces);
    }

    public function testResourceDictionaryContainsColorSpace(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('data', 3));
        $color = new IccColor($cs, 0.5, 0.3, 0.2);

        $stream = (new ContentStreamBuilder())
            ->setIccFillColor($color)
            ->build();

        $this->assertFalse($stream->resources->isEmpty());
        $this->assertSame($cs, $stream->resources->colorSpaces()['CS1']);
    }
}
