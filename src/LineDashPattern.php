<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class LineDashPattern
{
    /**
     * @param array<int, float> $dashArray
     */
    public function __construct(
        public readonly array $dashArray,
        public readonly float $dashPhase = 0.0,
    ) {}

    public static function solid(): self
    {
        return new self([], 0.0);
    }

    public function toPdfString(): string
    {
        $array = '[' . implode(' ', array_map(
            static fn(float $v): string => sprintf('%.2F', $v),
            $this->dashArray,
        )) . ']';

        return sprintf('%s %.2F d', $array, $this->dashPhase);
    }
}
