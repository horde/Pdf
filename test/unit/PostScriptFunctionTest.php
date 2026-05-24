<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\PostScriptFunction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PostScriptFunction::class)]
final class PostScriptFunctionTest extends TestCase
{
    public function testFunctionType(): void
    {
        $fn = new PostScriptFunction('1 exch sub', [0.0, 1.0], [0.0, 1.0]);
        $this->assertSame(4, $fn->functionType());
    }

    public function testStoresCode(): void
    {
        $code = '1 exch sub 0.5 mul';
        $fn = new PostScriptFunction($code, [0.0, 1.0], [0.0, 1.0]);
        $this->assertSame($code, $fn->code);
    }

    public function testDomain(): void
    {
        $fn = new PostScriptFunction('pop 0', [0.0, 1.0], [0.0, 1.0, 0.0, 1.0, 0.0, 1.0]);
        $this->assertSame([0.0, 1.0], $fn->domain());
    }

    public function testRange(): void
    {
        $range = [0.0, 1.0, 0.0, 1.0, 0.0, 1.0];
        $fn = new PostScriptFunction('pop 0', [0.0, 1.0], $range);
        $this->assertSame($range, $fn->range());
    }
}
