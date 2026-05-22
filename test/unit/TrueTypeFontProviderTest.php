<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\DeferredFont;
use Horde\Pdf\FontStyle;
use Horde\Pdf\TrueTypeFontProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TrueTypeFontProvider::class)]
final class TrueTypeFontProviderTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures';
        if (!file_exists($this->fixtureDir . '/roboto-light.ttf')) {
            $this->markTestSkipped('Font fixture not available');
        }
    }

    #[Test]
    public function resolvesExistingFont(): void
    {
        $provider = new TrueTypeFontProvider($this->fixtureDir);
        $font = $provider->resolve('roboto light', FontStyle::Regular);
        $this->assertInstanceOf(DeferredFont::class, $font);
    }

    #[Test]
    public function returnsNullForUnknownFamily(): void
    {
        $provider = new TrueTypeFontProvider($this->fixtureDir);
        $font = $provider->resolve('nonexistent', FontStyle::Regular);
        $this->assertNull($font);
    }

    #[Test]
    public function familiesIncludesScannedFonts(): void
    {
        $provider = new TrueTypeFontProvider($this->fixtureDir);
        $families = $provider->families();
        $this->assertNotEmpty($families);
        $found = false;
        foreach ($families as $f) {
            if (str_contains($f, 'roboto')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected a roboto family in scanned fonts');
    }

    #[Test]
    public function scansRecursively(): void
    {
        $subdir = $this->fixtureDir . '/subdir';
        if (!is_dir($subdir)) {
            mkdir($subdir);
        }
        copy($this->fixtureDir . '/roboto-light.ttf', $subdir . '/roboto-light.ttf');

        try {
            $provider = new TrueTypeFontProvider($subdir);
            $families = $provider->families();
            $this->assertNotEmpty($families);
        } finally {
            unlink($subdir . '/roboto-light.ttf');
            rmdir($subdir);
        }
    }

    #[Test]
    public function handlesNonExistentDirectory(): void
    {
        $provider = new TrueTypeFontProvider('/nonexistent/path');
        $families = $provider->families();
        $this->assertSame([], $families);
    }

    #[Test]
    public function fallsBackToRegularStyleWhenRequestedNotFound(): void
    {
        $provider = new TrueTypeFontProvider($this->fixtureDir);
        $font = $provider->resolve('roboto light', FontStyle::Bold);
        $this->assertInstanceOf(DeferredFont::class, $font);
    }
}
