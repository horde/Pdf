<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\CoreFontProvider;
use Horde\Pdf\FontStyle;
use Horde\Pdf\Type1Font;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CoreFontProvider::class)]
final class CoreFontProviderTest extends TestCase
{
    private CoreFontProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new CoreFontProvider();
    }

    #[Test]
    public function resolvesHelveticaRegular(): void
    {
        $font = $this->provider->resolve('helvetica', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica', $font->pdfName());
    }

    #[Test]
    public function resolvesHelveticaBold(): void
    {
        $font = $this->provider->resolve('helvetica', FontStyle::Bold);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica-Bold', $font->pdfName());
    }

    #[Test]
    public function resolvesHelveticaItalic(): void
    {
        $font = $this->provider->resolve('helvetica', FontStyle::Italic);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica-Oblique', $font->pdfName());
    }

    #[Test]
    public function resolvesHelveticaBoldItalic(): void
    {
        $font = $this->provider->resolve('helvetica', FontStyle::BoldItalic);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica-BoldOblique', $font->pdfName());
    }

    #[Test]
    public function resolvesCourierRegular(): void
    {
        $font = $this->provider->resolve('courier', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Courier', $font->pdfName());
    }

    #[Test]
    public function resolvesTimesRegular(): void
    {
        $font = $this->provider->resolve('times', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Times-Roman', $font->pdfName());
    }

    #[Test]
    public function resolvesArialAsHelvetica(): void
    {
        $font = $this->provider->resolve('arial', FontStyle::Bold);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica-Bold', $font->pdfName());
    }

    #[Test]
    public function resolvesSymbol(): void
    {
        $font = $this->provider->resolve('symbol', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Symbol', $font->pdfName());
    }

    #[Test]
    public function symbolIgnoresStyle(): void
    {
        $font = $this->provider->resolve('symbol', FontStyle::Bold);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Symbol', $font->pdfName());
    }

    #[Test]
    public function returnsNullForUnknownFamily(): void
    {
        $font = $this->provider->resolve('roboto', FontStyle::Regular);
        $this->assertNull($font);
    }

    #[Test]
    public function familiesReturnsCoreFamilyNames(): void
    {
        $families = $this->provider->families();
        $this->assertContains('helvetica', $families);
        $this->assertContains('courier', $families);
        $this->assertContains('times', $families);
        $this->assertContains('symbol', $families);
        $this->assertContains('zapfdingbats', $families);
        $this->assertCount(5, $families);
    }
}
