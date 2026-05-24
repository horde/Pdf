<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface PdfFunction
{
    public function functionType(): int;

    /** @return array<float> */
    public function domain(): array;

    /** @return array<float> */
    public function range(): array;
}
