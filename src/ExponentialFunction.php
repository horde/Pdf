<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ExponentialFunction implements PdfFunction
{
    /**
     * @param array<float> $c0 Function result when input is 0
     * @param array<float> $c1 Function result when input is 1
     */
    public function __construct(
        public readonly array $c0,
        public readonly array $c1,
        public readonly float $exponent = 1.0,
    ) {}

    public function functionType(): int
    {
        return 2;
    }

    public function domain(): array
    {
        return [0.0, 1.0];
    }

    public function range(): array
    {
        $range = [];
        $count = count($this->c0);
        for ($i = 0; $i < $count; $i++) {
            $range[] = min($this->c0[$i], $this->c1[$i]);
            $range[] = max($this->c0[$i], $this->c1[$i]);
        }
        return $range;
    }
}
