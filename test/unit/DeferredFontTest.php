<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\DeferredFont;
use Horde\Pdf\FontEncoding;
use Horde\Pdf\FontStyle;
use Horde\Pdf\TrueType\FontParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeferredFont::class)]
final class DeferredFontTest extends TestCase
{
    private DeferredFont $font;

    protected function setUp(): void
    {
        $path = __DIR__ . '/fixtures/roboto-light.ttf';
        if (!file_exists($path)) {
            $this->markTestSkipped('Font fixture not available');
        }
        $fontData = FontParser::parseFile($path);
        $this->font = new DeferredFont($fontData);
    }

    #[Test]
    public function pdfNameReturnsPostScriptName(): void
    {
        $this->assertStringContainsString('Roboto', $this->font->pdfName());
    }

    #[Test]
    public function encodingReturnsIdentityH(): void
    {
        $this->assertSame(FontEncoding::IdentityH, $this->font->encoding());
    }

    #[Test]
    public function styleReturnsRegularForLightFont(): void
    {
        $this->assertSame(FontStyle::Regular, $this->font->style());
    }

    #[Test]
    public function requiresEmbeddingReturnsTrue(): void
    {
        $this->assertTrue($this->font->requiresEmbedding());
    }

    #[Test]
    public function widthOfStringReturnsPositive(): void
    {
        $width = $this->font->widthOfString('Hello', 12.0);
        $this->assertGreaterThan(0.0, $width);
    }

    #[Test]
    public function encodeProducesTwoBytesPerCharacter(): void
    {
        $encoded = $this->font->encode('AB');
        $this->assertSame(4, strlen($encoded));
        $this->assertSame("\x00\x41\x00\x42", $encoded);
    }

    #[Test]
    public function fontDataReturnsUnderlyingData(): void
    {
        $data = $this->font->fontData();
        $this->assertGreaterThan(0, $data->unitsPerEm);
    }
}
