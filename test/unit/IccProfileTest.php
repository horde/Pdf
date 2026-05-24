<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\IccProfile;
use Horde\Pdf\PdfException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IccProfile::class)]
final class IccProfileTest extends TestCase
{
    public function testConstructorStoresData(): void
    {
        $data = str_repeat("\x00", 128);
        $profile = new IccProfile($data, 3);

        $this->assertSame($data, $profile->data);
        $this->assertSame(3, $profile->componentCount);
    }

    public function testRejectsZeroComponents(): void
    {
        $this->expectException(PdfException::class);
        new IccProfile('data', 0);
    }

    public function testRejectsFiveComponents(): void
    {
        $this->expectException(PdfException::class);
        new IccProfile('data', 5);
    }

    public function testAlternateSpaceGray(): void
    {
        $profile = new IccProfile('data', 1);
        $this->assertSame('DeviceGray', $profile->alternateSpace());
    }

    public function testAlternateSpaceRgb(): void
    {
        $profile = new IccProfile('data', 3);
        $this->assertSame('DeviceRGB', $profile->alternateSpace());
    }

    public function testAlternateSpaceCmyk(): void
    {
        $profile = new IccProfile('data', 4);
        $this->assertSame('DeviceCMYK', $profile->alternateSpace());
    }

    public function testAlternateSpaceTwoComponents(): void
    {
        $profile = new IccProfile('data', 2);
        $this->assertSame('DeviceRGB', $profile->alternateSpace());
    }
}
