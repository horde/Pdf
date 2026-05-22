<?php

declare(strict_types=1);

use Horde\Pdf\TrueType\FontParser;
use Horde\Pdf\TrueType\FontData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontParser::class)]
#[CoversClass(FontData::class)]
class FontParserTest extends TestCase
{
    private static string $fixturePath;

    public static function setUpBeforeClass(): void
    {
        self::$fixturePath = __DIR__ . '/../fixtures/roboto-light.ttf';
    }

    public function testParseReturnsValidFontData(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertInstanceOf(FontData::class, $font);
    }

    public function testUnitsPerEm(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertSame(2048, $font->unitsPerEm);
    }

    public function testPostScriptName(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertSame('Roboto-Light', $font->postScriptName);
    }

    public function testFontFamily(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertStringContainsString('Roboto', $font->fontFamily);
    }

    public function testFontSubfamily(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertNotEmpty($font->fontSubfamily);
    }

    public function testNumGlyphs(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertGreaterThan(100, $font->numGlyphs);
    }

    public function testAscenderIsPositive(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertGreaterThan(0, $font->ascender);
    }

    public function testDescenderIsNegative(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertLessThan(0, $font->descender);
    }

    public function testCmapContainsLatinLetters(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertArrayHasKey(65, $font->cmap); // 'A'
        $this->assertArrayHasKey(97, $font->cmap); // 'a'
        $this->assertArrayHasKey(48, $font->cmap); // '0'
    }

    public function testCmapMapsSpaceCodepoint(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertArrayHasKey(32, $font->cmap); // space
    }

    public function testHmtxHasWidthsForAllGlyphs(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertCount($font->numGlyphs, $font->hmtx);
    }

    public function testHmtxWidthsArePositive(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $glyphA = $font->glyphIdForCodepoint(65);
        $this->assertGreaterThan(0, $font->advanceWidth($glyphA));
    }

    public function testLocaHasCorrectCount(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertCount($font->numGlyphs + 1, $font->loca);
    }

    public function testGlyfRawNotEmpty(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertNotEmpty($font->glyfRaw);
    }

    public function testTableDirectoryContainsRequiredTables(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertArrayHasKey('head', $font->tableDirectory);
        $this->assertArrayHasKey('hhea', $font->tableDirectory);
        $this->assertArrayHasKey('maxp', $font->tableDirectory);
        $this->assertArrayHasKey('cmap', $font->tableDirectory);
        $this->assertArrayHasKey('hmtx', $font->tableDirectory);
        $this->assertArrayHasKey('loca', $font->tableDirectory);
        $this->assertArrayHasKey('glyf', $font->tableDirectory);
    }

    public function testGlyphIdForCodepointReturnsZeroForMissing(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertSame(0, $font->glyphIdForCodepoint(0x10FFFF));
    }

    public function testWeightClass(): void
    {
        $font = FontParser::parseFile(self::$fixturePath);
        $this->assertSame(300, $font->usWeightClass); // Light = 300
    }
}
