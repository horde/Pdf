<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\CoreFontProvider;
use Horde\Pdf\DeferredFont;
use Horde\Pdf\FontResolver;
use Horde\Pdf\FontStyle;
use Horde\Pdf\TrueTypeFontProvider;
use Horde\Pdf\Type1Font;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FontResolver::class)]
final class FontResolverTest extends TestCase
{
    #[Test]
    public function resolvesCoreFont(): void
    {
        $resolver = new FontResolver(new CoreFontProvider());
        $font = $resolver->resolve('Helvetica', FontStyle::Bold);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica-Bold', $font->pdfName());
    }

    #[Test]
    public function fallsBackToHelveticaForUnknownFamily(): void
    {
        $resolver = new FontResolver(new CoreFontProvider());
        $font = $resolver->resolve('NonExistentFont', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica', $font->pdfName());
    }

    #[Test]
    public function fallbackPreservesStyle(): void
    {
        $resolver = new FontResolver(new CoreFontProvider());
        $font = $resolver->resolve('NonExistentFont', FontStyle::BoldItalic);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica-BoldOblique', $font->pdfName());
    }

    #[Test]
    public function trueTypeProviderTakesPriority(): void
    {
        $fixtureDir = __DIR__ . '/fixtures';
        if (!file_exists($fixtureDir . '/roboto-light.ttf')) {
            $this->markTestSkipped('Font fixture not available');
        }

        $resolver = new FontResolver(
            new TrueTypeFontProvider($fixtureDir),
            new CoreFontProvider(),
        );

        $font = $resolver->resolve('Roboto Light', FontStyle::Regular);
        $this->assertInstanceOf(DeferredFont::class, $font);
    }

    #[Test]
    public function coreFontProviderWinsWhenListedFirst(): void
    {
        $fixtureDir = __DIR__ . '/fixtures';

        $resolver = new FontResolver(
            new CoreFontProvider(),
            new TrueTypeFontProvider($fixtureDir),
        );

        $font = $resolver->resolve('Helvetica', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
    }

    #[Test]
    public function caseInsensitiveResolution(): void
    {
        $resolver = new FontResolver(new CoreFontProvider());
        $font = $resolver->resolve('HELVETICA', FontStyle::Regular);
        $this->assertInstanceOf(Type1Font::class, $font);
        $this->assertSame('Helvetica', $font->pdfName());
    }
}
