<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class CoreFontProvider implements FontProvider
{
    private const ALIASES = [
        'arial' => 'helvetica',
    ];

    public function resolve(string $family, FontStyle $style): ?Font
    {
        $family = self::ALIASES[$family] ?? $family;

        if ($family === 'symbol' || $family === 'zapfdingbats') {
            $styleStr = '';
        } else {
            $styleStr = $style->value;
        }

        $key = $family . $styleStr;

        $case = CoreFont::tryFrom($key);
        if ($case === null) {
            return null;
        }

        return $case->toFont();
    }

    /**
     * @return array<string>
     */
    public function families(): array
    {
        return ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];
    }
}
