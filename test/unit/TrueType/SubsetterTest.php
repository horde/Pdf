<?php

declare(strict_types=1);

use Horde\Pdf\TrueType\FontParser;
use Horde\Pdf\TrueType\Subsetter;
use Horde\Pdf\TrueType\SubsetResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subsetter::class)]
#[CoversClass(SubsetResult::class)]
class SubsetterTest extends TestCase
{
    private static string $fixturePath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturePath = __DIR__ . '/../fixtures/roboto-light.ttf';
    }

    public function testSubsetReturnsSubsetResult(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65, 66, 67]); // A, B, C

        $this->assertInstanceOf(SubsetResult::class, $result);
    }

    public function testSubsetDataIsValidTtf(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65, 66, 67]);

        $this->assertNotEmpty($result->data);
        // Valid TTF starts with 0x00010000
        $version = (ord($result->data[0]) << 24) | (ord($result->data[1]) << 16)
            | (ord($result->data[2]) << 8) | ord($result->data[3]);
        $this->assertSame(0x00010000, $version);
    }

    public function testSubsetIsSmallerThanOriginal(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65, 66, 67]);

        $this->assertLessThan(strlen($font->rawData), strlen($result->data));
    }

    public function testSubsetContainsGlyphMapWithNotdef(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65]);

        $this->assertArrayHasKey(0, $result->glyphMap);
        $this->assertSame(0, $result->glyphMap[0]);
    }

    public function testSubsetWidthsHaveEntries(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65, 66, 67]);

        $this->assertNotEmpty($result->widths);
        $this->assertGreaterThan(0, $result->widths[1] ?? 0);
    }

    public function testSubsetCidToGidMapsCodepoints(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65, 66]);

        $this->assertArrayHasKey(65, $result->cidToGid);
        $this->assertArrayHasKey(66, $result->cidToGid);
        $this->assertGreaterThan(0, $result->cidToGid[65]);
    }

    public function testSubsetCanBeReparsed(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $result = Subsetter::subset($font, [65, 66, 67, 72, 101, 108, 111]);

        $reparsed = FontParser::parse($result->data);
        $this->assertSame(count($result->glyphMap), $reparsed->numGlyphs);
    }

    public function testSubsetPreservesWidthAccuracy(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $glyphA = $font->glyphIdForCodepoint(65);
        $originalWidth = $font->advanceWidth($glyphA);

        $result = Subsetter::subset($font, [65]);
        $newGidA = $result->cidToGid[65];

        $this->assertSame($originalWidth, $result->widths[$newGidA]);
    }
}
