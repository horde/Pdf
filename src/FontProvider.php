<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface FontProvider
{
    /**
     * @return ?Font Resolved font or null if this provider cannot resolve the request
     */
    public function resolve(string $family, FontStyle $style): ?Font;

    /**
     * @return array<string> Available family names (lowercase)
     */
    public function families(): array;
}
