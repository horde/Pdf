<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\ExponentialFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExponentialFunction::class)]
final class ExponentialFunctionTest extends TestCase
{
    public function testFunctionType(): void
    {
        $fn = new ExponentialFunction([1.0, 1.0, 1.0, 1.0], [0.0, 0.5, 0.8, 0.2]);
        $this->assertSame(2, $fn->functionType());
    }

    public function testDomain(): void
    {
        $fn = new ExponentialFunction([1.0, 1.0, 1.0], [0.0, 0.0, 0.0]);
        $this->assertSame([0.0, 1.0], $fn->domain());
    }

    public function testRange(): void
    {
        $fn = new ExponentialFunction([1.0, 0.5], [0.0, 1.0]);
        $range = $fn->range();
        $this->assertSame([0.0, 1.0, 0.5, 1.0], $range);
    }

    public function testStoresValues(): void
    {
        $fn = new ExponentialFunction([1.0, 1.0, 1.0], [0.0, 0.5, 0.8], 2.2);
        $this->assertSame([1.0, 1.0, 1.0], $fn->c0);
        $this->assertSame([0.0, 0.5, 0.8], $fn->c1);
        $this->assertSame(2.2, $fn->exponent);
    }

    public function testDefaultExponent(): void
    {
        $fn = new ExponentialFunction([1.0], [0.0]);
        $this->assertSame(1.0, $fn->exponent);
    }
}
