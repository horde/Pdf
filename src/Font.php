<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface Font
{
    public function pdfName(): string;

    public function encoding(): FontEncoding;

    public function style(): FontStyle;

    public function widthOfString(string $text, float $size): float;

    public function encode(string $text): string;

    public function requiresEmbedding(): bool;
}
