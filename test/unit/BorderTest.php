<?php

declare(strict_types=1);

use Horde\Pdf\Border;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Border::class)]
class BorderTest extends TestCase
{
    public function testNone(): void
    {
        $border = Border::none();
        $this->assertFalse($border->hasLeft());
        $this->assertFalse($border->hasRight());
        $this->assertFalse($border->hasTop());
        $this->assertFalse($border->hasBottom());
        $this->assertFalse($border->hasAny());
        $this->assertFalse($border->isFull());
    }

    public function testFull(): void
    {
        $border = Border::full();
        $this->assertTrue($border->hasLeft());
        $this->assertTrue($border->hasRight());
        $this->assertTrue($border->hasTop());
        $this->assertTrue($border->hasBottom());
        $this->assertTrue($border->hasAny());
        $this->assertTrue($border->isFull());
    }

    public function testCustomSides(): void
    {
        $border = Border::sides(left: true, bottom: true);
        $this->assertTrue($border->hasLeft());
        $this->assertFalse($border->hasRight());
        $this->assertFalse($border->hasTop());
        $this->assertTrue($border->hasBottom());
        $this->assertTrue($border->hasAny());
        $this->assertFalse($border->isFull());
    }

    public function testFromLegacyZero(): void
    {
        $border = Border::fromLegacy(0);
        $this->assertFalse($border->hasAny());
    }

    public function testFromLegacyOne(): void
    {
        $border = Border::fromLegacy(1);
        $this->assertTrue($border->isFull());
    }

    public function testFromLegacyString(): void
    {
        $border = Border::fromLegacy('LR');
        $this->assertTrue($border->hasLeft());
        $this->assertTrue($border->hasRight());
        $this->assertFalse($border->hasTop());
        $this->assertFalse($border->hasBottom());
    }

    public function testFromLegacyAllSides(): void
    {
        $border = Border::fromLegacy('LTRB');
        $this->assertTrue($border->isFull());
    }

    public function testFromLegacyEmptyString(): void
    {
        $border = Border::fromLegacy('');
        $this->assertFalse($border->hasAny());
    }
}
