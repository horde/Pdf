<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class FontResolver
{
    /** @var array<FontProvider> */
    private readonly array $providers;

    public function __construct(FontProvider ...$providers)
    {
        $this->providers = $providers;
    }

    public function resolve(string $family, FontStyle $style): Font
    {
        $normalized = strtolower(trim($family));

        foreach ($this->providers as $provider) {
            $font = $provider->resolve($normalized, $style);
            if ($font !== null) {
                return $font;
            }
        }

        return $this->fallback($style);
    }

    private function fallback(FontStyle $style): Font
    {
        $coreFont = match ($style) {
            FontStyle::Bold => CoreFont::HelveticaBold,
            FontStyle::Italic => CoreFont::HelveticaItalic,
            FontStyle::BoldItalic => CoreFont::HelveticaBoldItalic,
            default => CoreFont::Helvetica,
        };

        return $coreFont->toFont();
    }
}
