<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\DeviceCmyk;
use Horde\Pdf\ExponentialFunction;
use Horde\Pdf\SeparationColorSpace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SeparationColorSpace::class)]
final class SeparationColorSpaceTest extends TestCase
{
    public function testPdfName(): void
    {
        $cs = new SeparationColorSpace(
            'PANTONE 300 C',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
        $this->assertSame('Separation', $cs->pdfName());
    }

    public function testComponentCount(): void
    {
        $cs = new SeparationColorSpace(
            'SpotGreen',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [0.5, 0.0, 1.0, 0.0]),
        );
        $this->assertSame(1, $cs->componentCount());
    }

    public function testStoresColorantName(): void
    {
        $cs = new SeparationColorSpace(
            'PANTONE 300 C',
            new DeviceCmyk(),
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
        $this->assertSame('PANTONE 300 C', $cs->colorantName);
    }

    public function testStoresAlternateSpace(): void
    {
        $alt = new DeviceCmyk();
        $cs = new SeparationColorSpace(
            'Spot',
            $alt,
            new ExponentialFunction([0.0, 0.0, 0.0, 0.0], [1.0, 0.0, 0.0, 0.0]),
        );
        $this->assertSame($alt, $cs->alternateSpace);
    }
}
