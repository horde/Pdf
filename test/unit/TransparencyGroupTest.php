<?php

declare(strict_types=1);

use Horde\Pdf\ColorSpace;
use Horde\Pdf\DeviceRgb;
use Horde\Pdf\TransparencyGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TransparencyGroup::class)]
class TransparencyGroupTest extends TestCase
{
    public function testDefaults(): void
    {
        $group = new TransparencyGroup();
        $this->assertNull($group->colorSpace);
        $this->assertFalse($group->isolated);
        $this->assertFalse($group->knockout);
    }

    public function testWithAllProperties(): void
    {
        $cs = new DeviceRgb();
        $group = new TransparencyGroup(
            colorSpace: $cs,
            isolated: true,
            knockout: true,
        );
        $this->assertSame($cs, $group->colorSpace);
        $this->assertTrue($group->isolated);
        $this->assertTrue($group->knockout);
    }

    public function testIsolatedOnly(): void
    {
        $group = new TransparencyGroup(isolated: true);
        $this->assertNull($group->colorSpace);
        $this->assertTrue($group->isolated);
        $this->assertFalse($group->knockout);
    }
}
