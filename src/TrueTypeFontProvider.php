<?php

declare(strict_types=1);

namespace Horde\Pdf;

use Horde\Pdf\TrueType\FontParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class TrueTypeFontProvider implements FontProvider
{
    /** @var array<string> */
    private readonly array $directories;

    /** @var ?array<string, array<string, string>> [family][style_value] => filePath */
    private ?array $index = null;

    public function __construct(string ...$directories)
    {
        $this->directories = $directories;
    }

    public function resolve(string $family, FontStyle $style): ?Font
    {
        $this->buildIndex();

        $entry = $this->index[$family][$style->value] ?? null;

        if ($entry === null && $style !== FontStyle::Regular) {
            $entry = $this->index[$family][FontStyle::Regular->value] ?? null;
        }

        if ($entry === null) {
            return null;
        }

        $fontData = FontParser::parseFile($entry);
        return new DeferredFont($fontData);
    }

    /**
     * @return array<string>
     */
    public function families(): array
    {
        $this->buildIndex();
        return array_keys($this->index);
    }

    private function buildIndex(): void
    {
        if ($this->index !== null) {
            return;
        }

        $this->index = [];

        foreach ($this->directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($dir),
            );

            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                if (strtolower($file->getExtension()) !== 'ttf') {
                    continue;
                }

                $this->indexFont($file->getPathname());
            }
        }
    }

    private function indexFont(string $path): void
    {
        try {
            $fontData = FontParser::parseFile($path);
        } catch (Throwable) {
            return;
        }

        $family = strtolower(trim($fontData->fontFamily));
        if ($family === '') {
            return;
        }

        $style = $this->detectStyle($fontData);
        $this->index[$family][$style->value] = $path;
    }

    private function detectStyle(TrueType\FontData $fontData): FontStyle
    {
        $isBold = $fontData->usWeightClass >= 700;
        $isItalic = $fontData->italicAngle !== 0
            || ($fontData->fsSelection & 0x0001) !== 0;

        if ($isBold && $isItalic) {
            return FontStyle::BoldItalic;
        }
        if ($isBold) {
            return FontStyle::Bold;
        }
        if ($isItalic) {
            return FontStyle::Italic;
        }
        return FontStyle::Regular;
    }
}
