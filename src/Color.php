<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class Color
{
    private function __construct(
        private readonly ColorModel $space,
        private readonly float $c1,
        private readonly float $c2,
        private readonly float $c3,
        private readonly float $c4,
    ) {}

    public static function rgb(float $r, float $g, float $b): self
    {
        return new self(ColorModel::Rgb, $r, $g, $b, 0.0);
    }

    public static function cmyk(float $c, float $m, float $y, float $k): self
    {
        return new self(ColorModel::Cmyk, $c, $m, $y, $k);
    }

    public static function gray(float $g): self
    {
        return new self(ColorModel::Gray, $g, 0.0, 0.0, 0.0);
    }

    public static function hex(string $hex): self
    {
        if (str_starts_with($hex, '#')) {
            $hex = substr($hex, 1);
        }

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        return new self(ColorModel::Rgb, $r, $g, $b, 0.0);
    }

    public function colorModel(): ColorModel
    {
        return $this->space;
    }

    public function toPdfFillString(): string
    {
        return match ($this->space) {
            ColorModel::Rgb, ColorModel::Hex => sprintf('%.3F %.3F %.3F rg', $this->c1, $this->c2, $this->c3),
            ColorModel::Cmyk => sprintf('%.3F %.3F %.3F %.3F k', $this->c1, $this->c2, $this->c3, $this->c4),
            ColorModel::Gray => sprintf('%.3F g', $this->c1),
        };
    }

    public function toPdfStrokeString(): string
    {
        return match ($this->space) {
            ColorModel::Rgb, ColorModel::Hex => sprintf('%.3F %.3F %.3F RG', $this->c1, $this->c2, $this->c3),
            ColorModel::Cmyk => sprintf('%.3F %.3F %.3F %.3F K', $this->c1, $this->c2, $this->c3, $this->c4),
            ColorModel::Gray => sprintf('%.3F G', $this->c1),
        };
    }
}
