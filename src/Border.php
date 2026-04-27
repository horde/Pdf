<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class Border
{
    private function __construct(
        private readonly bool $left,
        private readonly bool $right,
        private readonly bool $top,
        private readonly bool $bottom,
    ) {}

    public static function none(): self
    {
        return new self(false, false, false, false);
    }

    public static function full(): self
    {
        return new self(true, true, true, true);
    }

    public static function sides(
        bool $left = false,
        bool $right = false,
        bool $top = false,
        bool $bottom = false,
    ): self {
        return new self($left, $right, $top, $bottom);
    }

    /**
     * Parse from the legacy border parameter (0, 1, or string of L/R/T/B).
     */
    public static function fromLegacy(int|string $border): self
    {
        if ($border === 0 || $border === '') {
            return self::none();
        }
        if ($border === 1) {
            return self::full();
        }
        $s = strtoupper((string) $border);
        return new self(
            str_contains($s, 'L'),
            str_contains($s, 'R'),
            str_contains($s, 'T'),
            str_contains($s, 'B'),
        );
    }

    public function hasLeft(): bool
    {
        return $this->left;
    }

    public function hasRight(): bool
    {
        return $this->right;
    }

    public function hasTop(): bool
    {
        return $this->top;
    }

    public function hasBottom(): bool
    {
        return $this->bottom;
    }

    public function hasAny(): bool
    {
        return $this->left || $this->right || $this->top || $this->bottom;
    }

    public function isFull(): bool
    {
        return $this->left && $this->right && $this->top && $this->bottom;
    }
}
