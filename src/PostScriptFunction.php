<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class PostScriptFunction implements PdfFunction
{
    /**
     * @param array<float> $domain Input domain pairs [min0, max0, min1, max1, ...]
     * @param array<float> $range Output range pairs [min0, max0, min1, max1, ...]
     */
    public function __construct(
        public readonly string $code,
        public readonly array $domain,
        public readonly array $range,
    ) {}

    public function functionType(): int
    {
        return 4;
    }

    public function domain(): array
    {
        return $this->domain;
    }

    public function range(): array
    {
        return $this->range;
    }
}
