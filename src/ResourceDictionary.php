<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ResourceDictionary
{
    /** @var array<string, Font> */
    private array $fonts = [];

    /** @var array<string, ImageXObject> */
    private array $images = [];

    /** @var array<string, ExtGState> */
    private array $extGraphicsStates = [];

    /** @var array<string, FormXObject> */
    private array $forms = [];

    /** @var array<string, IccBasedColorSpace> */
    private array $colorSpaces = [];

    public function addFont(string $name, Font $font): void
    {
        $this->fonts[$name] = $font;
    }

    public function addImage(string $name, ImageXObject $image): void
    {
        $this->images[$name] = $image;
    }

    public function addExtGState(string $name, ExtGState $gs): void
    {
        $this->extGraphicsStates[$name] = $gs;
    }

    public function addForm(string $name, FormXObject $form): void
    {
        $this->forms[$name] = $form;
    }

    public function addColorSpace(string $name, IccBasedColorSpace $cs): void
    {
        $this->colorSpaces[$name] = $cs;
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

    /**
     * @return array<string, ExtGState>
     */
    public function extGraphicsStates(): array
    {
        return $this->extGraphicsStates;
    }

    /**
     * @return array<string, FormXObject>
     */
    public function forms(): array
    {
        return $this->forms;
    }

    /**
     * @return array<string, IccBasedColorSpace>
     */
    public function colorSpaces(): array
    {
        return $this->colorSpaces;
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

        foreach ($other->extGraphicsStates as $name => $gs) {
            if (!isset($this->extGraphicsStates[$name])) {
                $this->extGraphicsStates[$name] = $gs;
            }
        }

        foreach ($other->forms as $name => $form) {
            if (!isset($this->forms[$name])) {
                $this->forms[$name] = $form;
            }
        }

        foreach ($other->colorSpaces as $name => $cs) {
            if (!isset($this->colorSpaces[$name])) {
                $this->colorSpaces[$name] = $cs;
            }
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->fonts)
            && empty($this->images)
            && empty($this->extGraphicsStates)
            && empty($this->forms)
            && empty($this->colorSpaces);
    }
}
