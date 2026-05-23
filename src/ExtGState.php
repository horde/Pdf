<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ExtGState
{
    public function __construct(
        public readonly ?float $fillAlpha = null,
        public readonly ?float $strokeAlpha = null,
        public readonly ?BlendMode $blendMode = null,
        public readonly ?bool $overprint = null,
    ) {}

    public static function alpha(float $fill, ?float $stroke = null): self
    {
        return new self(fillAlpha: $fill, strokeAlpha: $stroke ?? $fill);
    }

    public static function blendMode(BlendMode $mode): self
    {
        return new self(blendMode: $mode);
    }

    public function key(): string
    {
        return sprintf(
            'ca:%s|CA:%s|BM:%s|OP:%s',
            $this->fillAlpha !== null ? sprintf('%.3f', $this->fillAlpha) : '-',
            $this->strokeAlpha !== null ? sprintf('%.3f', $this->strokeAlpha) : '-',
            $this->blendMode?->value ?? '-',
            $this->overprint !== null ? ($this->overprint ? '1' : '0') : '-',
        );
    }
}
