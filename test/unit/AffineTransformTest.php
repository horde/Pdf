<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\AffineTransform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AffineTransform::class)]
final class AffineTransformTest extends TestCase
{
    #[Test]
    public function identityMatrix(): void
    {
        $t = AffineTransform::identity();
        $this->assertSame(1.0, $t->a);
        $this->assertSame(0.0, $t->b);
        $this->assertSame(0.0, $t->c);
        $this->assertSame(1.0, $t->d);
        $this->assertSame(0.0, $t->e);
        $this->assertSame(0.0, $t->f);
    }

    #[Test]
    public function translateMatrix(): void
    {
        $t = AffineTransform::translate(10.0, 20.0);
        $this->assertSame(1.0, $t->a);
        $this->assertSame(0.0, $t->b);
        $this->assertSame(0.0, $t->c);
        $this->assertSame(1.0, $t->d);
        $this->assertSame(10.0, $t->e);
        $this->assertSame(20.0, $t->f);
    }

    #[Test]
    public function rotate90Degrees(): void
    {
        $t = AffineTransform::rotate(90.0);
        $this->assertEqualsWithDelta(0.0, $t->a, 1e-10);
        $this->assertEqualsWithDelta(1.0, $t->b, 1e-10);
        $this->assertEqualsWithDelta(-1.0, $t->c, 1e-10);
        $this->assertEqualsWithDelta(0.0, $t->d, 1e-10);
    }

    #[Test]
    public function rotate45Degrees(): void
    {
        $t = AffineTransform::rotate(45.0);
        $cos45 = cos(deg2rad(45.0));
        $sin45 = sin(deg2rad(45.0));
        $this->assertEqualsWithDelta($cos45, $t->a, 1e-10);
        $this->assertEqualsWithDelta($sin45, $t->b, 1e-10);
        $this->assertEqualsWithDelta(-$sin45, $t->c, 1e-10);
        $this->assertEqualsWithDelta($cos45, $t->d, 1e-10);
    }

    #[Test]
    public function scaleUniform(): void
    {
        $t = AffineTransform::scale(2.0);
        $this->assertSame(2.0, $t->a);
        $this->assertSame(0.0, $t->b);
        $this->assertSame(0.0, $t->c);
        $this->assertSame(2.0, $t->d);
    }

    #[Test]
    public function scaleNonUniform(): void
    {
        $t = AffineTransform::scale(3.0, 0.5);
        $this->assertSame(3.0, $t->a);
        $this->assertSame(0.5, $t->d);
    }

    #[Test]
    public function skewX(): void
    {
        $t = AffineTransform::skewX(45.0);
        $this->assertSame(1.0, $t->a);
        $this->assertEqualsWithDelta(tan(deg2rad(45.0)), $t->c, 1e-10);
    }

    #[Test]
    public function skewY(): void
    {
        $t = AffineTransform::skewY(30.0);
        $this->assertEqualsWithDelta(tan(deg2rad(30.0)), $t->b, 1e-10);
        $this->assertSame(1.0, $t->d);
    }

    #[Test]
    public function multiplyWithIdentityReturnsSame(): void
    {
        $t = AffineTransform::translate(5.0, 10.0);
        $result = $t->multiply(AffineTransform::identity());
        $this->assertEqualsWithDelta($t->a, $result->a, 1e-10);
        $this->assertEqualsWithDelta($t->e, $result->e, 1e-10);
        $this->assertEqualsWithDelta($t->f, $result->f, 1e-10);
    }

    #[Test]
    public function multiplyTranslateAndRotate(): void
    {
        $translate = AffineTransform::translate(100.0, 200.0);
        $rotate = AffineTransform::rotate(90.0);
        $result = $translate->multiply($rotate);

        // [1 0 0 1 100 200] * [0 1 -1 0 0 0]
        // a = 1*0 + 0*(-1) = 0
        // b = 1*1 + 0*0 = 1
        // c = 0*0 + 1*(-1) = -1
        // d = 0*1 + 1*0 = 0
        // e = 100*0 + 200*(-1) + 0 = -200
        // f = 100*1 + 200*0 + 0 = 100
        $this->assertEqualsWithDelta(0.0, $result->a, 1e-10);
        $this->assertEqualsWithDelta(1.0, $result->b, 1e-10);
        $this->assertEqualsWithDelta(-1.0, $result->c, 1e-10);
        $this->assertEqualsWithDelta(0.0, $result->d, 1e-10);
        $this->assertEqualsWithDelta(-200.0, $result->e, 1e-10);
        $this->assertEqualsWithDelta(100.0, $result->f, 1e-10);
    }

    #[Test]
    public function toPdfOperatorFormat(): void
    {
        $t = AffineTransform::translate(10.5, 20.3);
        $op = $t->toPdfOperator();
        $this->assertSame('1.0000 0.0000 0.0000 1.0000 10.5000 20.3000 cm', $op);
    }

    #[Test]
    public function toPdfOperatorRotation(): void
    {
        $t = AffineTransform::rotate(90.0);
        $op = $t->toPdfOperator();
        $this->assertStringContainsString('cm', $op);
        $this->assertStringContainsString('1.0000', $op);
    }
}
