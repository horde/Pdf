<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class AffineTransform
{
    public function __construct(
        public readonly float $a,
        public readonly float $b,
        public readonly float $c,
        public readonly float $d,
        public readonly float $e,
        public readonly float $f,
    ) {}

    public static function identity(): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, 0.0, 0.0);
    }

    public static function translate(float $tx, float $ty): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, $tx, $ty);
    }

    public static function rotate(float $angleDeg): self
    {
        $rad = deg2rad($angleDeg);
        $cos = cos($rad);
        $sin = sin($rad);
        return new self($cos, $sin, -$sin, $cos, 0.0, 0.0);
    }

    public static function scale(float $sx, ?float $sy = null): self
    {
        $sy ??= $sx;
        return new self($sx, 0.0, 0.0, $sy, 0.0, 0.0);
    }

    public static function skewX(float $angleDeg): self
    {
        return new self(1.0, 0.0, tan(deg2rad($angleDeg)), 1.0, 0.0, 0.0);
    }

    public static function skewY(float $angleDeg): self
    {
        return new self(1.0, tan(deg2rad($angleDeg)), 0.0, 1.0, 0.0, 0.0);
    }

    public function multiply(self $other): self
    {
        return new self(
            $this->a * $other->a + $this->b * $other->c,
            $this->a * $other->b + $this->b * $other->d,
            $this->c * $other->a + $this->d * $other->c,
            $this->c * $other->b + $this->d * $other->d,
            $this->e * $other->a + $this->f * $other->c + $other->e,
            $this->e * $other->b + $this->f * $other->d + $other->f,
        );
    }

    public function toPdfOperator(): string
    {
        return sprintf(
            '%.4F %.4F %.4F %.4F %.4F %.4F cm',
            $this->a,
            $this->b,
            $this->c,
            $this->d,
            $this->e,
            $this->f,
        );
    }
}
