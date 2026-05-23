<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\BlendMode;
use Horde\Pdf\ExtGState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtGState::class)]
final class ExtGStateTest extends TestCase
{
    #[Test]
    public function constructorStoresValues(): void
    {
        $gs = new ExtGState(fillAlpha: 0.5, strokeAlpha: 0.8, blendMode: BlendMode::Multiply);
        $this->assertSame(0.5, $gs->fillAlpha);
        $this->assertSame(0.8, $gs->strokeAlpha);
        $this->assertSame(BlendMode::Multiply, $gs->blendMode);
        $this->assertNull($gs->overprint);
    }

    #[Test]
    public function alphaFactory(): void
    {
        $gs = ExtGState::alpha(0.3);
        $this->assertSame(0.3, $gs->fillAlpha);
        $this->assertSame(0.3, $gs->strokeAlpha);
        $this->assertNull($gs->blendMode);
    }

    #[Test]
    public function alphaFactoryWithSeparateStroke(): void
    {
        $gs = ExtGState::alpha(0.5, 0.9);
        $this->assertSame(0.5, $gs->fillAlpha);
        $this->assertSame(0.9, $gs->strokeAlpha);
    }

    #[Test]
    public function blendModeFactory(): void
    {
        $gs = ExtGState::blendMode(BlendMode::Screen);
        $this->assertNull($gs->fillAlpha);
        $this->assertNull($gs->strokeAlpha);
        $this->assertSame(BlendMode::Screen, $gs->blendMode);
    }

    #[Test]
    public function keyIsConsistentForSameValues(): void
    {
        $gs1 = ExtGState::alpha(0.5);
        $gs2 = ExtGState::alpha(0.5);
        $this->assertSame($gs1->key(), $gs2->key());
    }

    #[Test]
    public function keyDiffersForDifferentValues(): void
    {
        $gs1 = ExtGState::alpha(0.5);
        $gs2 = ExtGState::alpha(0.7);
        $this->assertNotSame($gs1->key(), $gs2->key());
    }

    #[Test]
    public function keyIncludesBlendMode(): void
    {
        $gs1 = ExtGState::blendMode(BlendMode::Multiply);
        $gs2 = ExtGState::blendMode(BlendMode::Screen);
        $this->assertNotSame($gs1->key(), $gs2->key());
    }

    #[Test]
    public function overprintStored(): void
    {
        $gs = new ExtGState(overprint: true);
        $this->assertTrue($gs->overprint);
    }
}
