<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class Rectangle
{
    public function __construct(
        public readonly float $llx,
        public readonly float $lly,
        public readonly float $urx,
        public readonly float $ury,
    ) {}

    public static function fromDimensions(float $width, float $height): self
    {
        return new self(0.0, 0.0, $width, $height);
    }

    public static function fromPageFormat(
        PageFormat $format,
        Orientation $orientation = Orientation::Portrait,
    ): self {
        [$w, $h] = $format->dimensions();

        if ($orientation === Orientation::Landscape) {
            return new self(0.0, 0.0, $h, $w);
        }

        return new self(0.0, 0.0, $w, $h);
    }

    public function width(): float
    {
        return $this->urx - $this->llx;
    }

    public function height(): float
    {
        return $this->ury - $this->lly;
    }

    public function toPdfArray(): string
    {
        return sprintf(
            '[%.2F %.2F %.2F %.2F]',
            $this->llx,
            $this->lly,
            $this->urx,
            $this->ury,
        );
    }
}
