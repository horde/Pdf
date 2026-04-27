<?php

declare(strict_types=1);

use Horde\Pdf\LayoutMode;
use Horde\Pdf\ViewerPreferences;
use Horde\Pdf\ZoomMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViewerPreferences::class)]
class ViewerPreferencesTest extends TestCase
{
    public function testDefaults(): void
    {
        $prefs = new ViewerPreferences();
        $this->assertSame(ZoomMode::DefaultMode, $prefs->zoomMode);
        $this->assertSame(LayoutMode::DefaultMode, $prefs->layoutMode);
        $this->assertNull($prefs->zoomPercent);
    }

    public function testCustomValues(): void
    {
        $prefs = new ViewerPreferences(
            zoomMode: ZoomMode::FullWidth,
            layoutMode: LayoutMode::Two,
            zoomPercent: 150,
        );
        $this->assertSame(ZoomMode::FullWidth, $prefs->zoomMode);
        $this->assertSame(LayoutMode::Two, $prefs->layoutMode);
        $this->assertSame(150, $prefs->zoomPercent);
    }
}
