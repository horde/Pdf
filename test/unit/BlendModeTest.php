<?php

declare(strict_types=1);

namespace Horde\Pdf\Test\Unit;

use Horde\Pdf\BlendMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BlendMode::class)]
final class BlendModeTest extends TestCase
{
    #[Test]
    public function normalHasCorrectValue(): void
    {
        $this->assertSame('Normal', BlendMode::Normal->value);
    }

    #[Test]
    public function multiplyHasCorrectValue(): void
    {
        $this->assertSame('Multiply', BlendMode::Multiply->value);
    }

    #[Test]
    public function screenHasCorrectValue(): void
    {
        $this->assertSame('Screen', BlendMode::Screen->value);
    }

    #[Test]
    public function allCasesMatchPdfSpecNames(): void
    {
        $expected = [
            'Normal', 'Multiply', 'Screen', 'Overlay',
            'Darken', 'Lighten', 'ColorDodge', 'ColorBurn',
            'HardLight', 'SoftLight', 'Difference', 'Exclusion',
        ];

        $actual = array_map(fn(BlendMode $m) => $m->value, BlendMode::cases());
        $this->assertSame($expected, $actual);
    }

    #[Test]
    public function canBeCreatedFromString(): void
    {
        $mode = BlendMode::from('Multiply');
        $this->assertSame(BlendMode::Multiply, $mode);
    }
}
