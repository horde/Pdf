<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ResourceDictionary
{
    /** @var array<string, Font> */
    private array $fonts = [];

    /** @var array<string, ImageXObject> */
    private array $images = [];

    public function addFont(string $name, Font $font): void
    {
        $this->fonts[$name] = $font;
    }

    public function addImage(string $name, ImageXObject $image): void
    {
        $this->images[$name] = $image;
    }

    /**
     * @return array<string, Font>
     */
    public function fonts(): array
    {
        return $this->fonts;
    }

    /**
     * @return array<string, ImageXObject>
     */
    public function images(): array
    {
        return $this->images;
    }

    public function merge(self $other): void
    {
        foreach ($other->fonts as $name => $font) {
            if (!isset($this->fonts[$name])) {
                $this->fonts[$name] = $font;
            }
        }

        foreach ($other->images as $name => $image) {
            if (!isset($this->images[$name])) {
                $this->images[$name] = $image;
            }
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->fonts) && empty($this->images);
    }
}
