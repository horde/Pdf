<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ContentStreamBuilder
{
    /** @var array<int, string> */
    private array $operators = [];

    private bool $inTextObject = false;
    private int $graphicsStateDepth = 0;
    private int $markedContentDepth = 0;

    /** @var array<string, Font> */
    private array $fontMap = [];

    /** @var array<string, ImageXObject> */
    private array $imageMap = [];

    /** @var array<string, FormXObject> */
    private array $formMap = [];

    private int $fontCounter = 0;
    private int $imageCounter = 0;
    private int $formCounter = 0;

    /** @var array<string, string> pdfName → local name (F1, F2, ...) */
    private array $fontNameIndex = [];

    /** @var array<int, string> spl_object_id → local name (I1, I2, ...) */
    private array $imageNameIndex = [];

    /** @var array<int, string> spl_object_id → local name (X1, X2, ...) */
    private array $formNameIndex = [];

    /** @var array<string, ColorSpace> */
    private array $colorSpaceMap = [];

    private int $colorSpaceCounter = 0;

    /** @var array<int, string> spl_object_id → local name (CS1, CS2, ...) */
    private array $colorSpaceNameIndex = [];

    // --- Graphics state ---

    public function save(): self
    {
        $this->operators[] = 'q';
        $this->graphicsStateDepth++;
        return $this;
    }

    public function restore(): self
    {
        if ($this->graphicsStateDepth <= 0) {
            throw new PdfException('Unbalanced restore: no matching save');
        }
        $this->operators[] = 'Q';
        $this->graphicsStateDepth--;
        return $this;
    }

    // --- Line style ---

    public function setLineWidth(float $width): self
    {
        $this->operators[] = sprintf('%.2F w', $width);
        return $this;
    }

    public function setLineCap(LineCap $cap): self
    {
        $this->operators[] = sprintf('%d J', $cap->value);
        return $this;
    }

    public function setDashPattern(LineDashPattern $pattern): self
    {
        $this->operators[] = $pattern->toPdfString();
        return $this;
    }

    // --- Color ---

    public function setFillColor(Color $color): self
    {
        $this->operators[] = $color->toPdfFillString();
        return $this;
    }

    public function setStrokeColor(Color $color): self
    {
        $this->operators[] = $color->toPdfStrokeString();
        return $this;
    }

    public function setIccFillColor(IccColor $color): self
    {
        $localName = $this->registerColorSpace($color->colorSpace());
        $this->operators[] = $color->toPdfFillString($localName);
        return $this;
    }

    public function setIccStrokeColor(IccColor $color): self
    {
        $localName = $this->registerColorSpace($color->colorSpace());
        $this->operators[] = $color->toPdfStrokeString($localName);
        return $this;
    }

    public function setSeparationFillColor(SeparationColor $color): self
    {
        $localName = $this->registerColorSpace($color->colorSpace());
        $this->operators[] = $color->toPdfFillString($localName);
        return $this;
    }

    public function setSeparationStrokeColor(SeparationColor $color): self
    {
        $localName = $this->registerColorSpace($color->colorSpace());
        $this->operators[] = $color->toPdfStrokeString($localName);
        return $this;
    }

    public function setDeviceNFillColor(DeviceNColor $color): self
    {
        $localName = $this->registerColorSpace($color->colorSpace());
        $this->operators[] = $color->toPdfFillString($localName);
        return $this;
    }

    public function setDeviceNStrokeColor(DeviceNColor $color): self
    {
        $localName = $this->registerColorSpace($color->colorSpace());
        $this->operators[] = $color->toPdfStrokeString($localName);
        return $this;
    }

    // --- Path construction ---

    public function moveTo(float $x, float $y): self
    {
        $this->operators[] = sprintf('%.2F %.2F m', $x, $y);
        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        $this->operators[] = sprintf('%.2F %.2F l', $x, $y);
        return $this;
    }

    public function curveTo(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $x3,
        float $y3,
    ): self {
        $this->operators[] = sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            $x1,
            $y1,
            $x2,
            $y2,
            $x3,
            $y3,
        );
        return $this;
    }

    public function rect(float $x, float $y, float $w, float $h): self
    {
        $this->operators[] = sprintf('%.2F %.2F %.2F %.2F re', $x, $y, $w, $h);
        return $this;
    }

    public function closePath(): self
    {
        $this->operators[] = 'h';
        return $this;
    }

    // --- Path painting ---

    public function stroke(): self
    {
        $this->operators[] = 'S';
        return $this;
    }

    public function fill(): self
    {
        $this->operators[] = 'f';
        return $this;
    }

    public function fillAndStroke(): self
    {
        $this->operators[] = 'B';
        return $this;
    }

    public function clip(): self
    {
        $this->operators[] = 'W n';
        return $this;
    }

    // --- Text ---

    public function beginText(): self
    {
        if ($this->inTextObject) {
            throw new PdfException('Already inside a text object');
        }
        $this->operators[] = 'BT';
        $this->inTextObject = true;
        return $this;
    }

    public function endText(): self
    {
        if (!$this->inTextObject) {
            throw new PdfException('Not inside a text object');
        }
        $this->operators[] = 'ET';
        $this->inTextObject = false;
        return $this;
    }

    public function setFont(Font $font, float $size): self
    {
        $localName = $this->registerFont($font);
        $this->operators[] = sprintf('/%s %.2F Tf', $localName, $size);
        return $this;
    }

    public function showText(string $text): self
    {
        if (!$this->inTextObject) {
            throw new PdfException('showText must be called inside a text object (between beginText/endText)');
        }
        $this->operators[] = sprintf('(%s) Tj', self::escapeString($text));
        return $this;
    }

    public function moveTextPosition(float $tx, float $ty): self
    {
        $this->operators[] = sprintf('%.2F %.2F Td', $tx, $ty);
        return $this;
    }

    public function setCharSpacing(float $spacing): self
    {
        $this->operators[] = sprintf('%.2F Tc', $spacing);
        return $this;
    }

    public function setWordSpacing(float $spacing): self
    {
        $this->operators[] = sprintf('%.2F Tw', $spacing);
        return $this;
    }

    public function setTextRenderingMode(TextRenderingMode $mode): self
    {
        $this->operators[] = sprintf('%d Tr', $mode->value);
        return $this;
    }

    // --- Images ---

    public function drawImage(
        ImageXObject $image,
        float $x,
        float $y,
        float $w,
        float $h,
    ): self {
        $localName = $this->registerImage($image);
        $this->operators[] = sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q',
            $w,
            $h,
            $x,
            $y,
            $localName,
        );
        return $this;
    }

    // --- Marked Content ---

    public function beginMarkedContent(string $tag, int $mcid): self
    {
        $this->operators[] = sprintf('/%s <</MCID %d>> BDC', $tag, $mcid);
        $this->markedContentDepth++;
        return $this;
    }

    public function beginMarkedContentSimple(string $tag): self
    {
        $this->operators[] = sprintf('/%s BMC', $tag);
        $this->markedContentDepth++;
        return $this;
    }

    public function endMarkedContent(): self
    {
        if ($this->markedContentDepth <= 0) {
            throw new PdfException('Unbalanced endMarkedContent: no matching beginMarkedContent');
        }
        $this->operators[] = 'EMC';
        $this->markedContentDepth--;
        return $this;
    }

    // --- Form XObjects ---

    public function drawFormXObject(
        FormXObject $form,
        ?AffineTransform $transform = null,
    ): self {
        $localName = $this->registerForm($form);
        if ($transform !== null) {
            $this->operators[] = sprintf('q %s /%s Do Q', $transform->toPdfOperator(), $localName);
        } else {
            $this->operators[] = sprintf('q /%s Do Q', $localName);
        }
        return $this;
    }

    // --- Build ---

    public function build(): ContentStream
    {
        if ($this->inTextObject) {
            throw new PdfException('Unclosed text object at build time');
        }

        if ($this->graphicsStateDepth !== 0) {
            throw new PdfException(
                sprintf('Unbalanced graphics state: %d save(s) without matching restore', $this->graphicsStateDepth)
            );
        }

        if ($this->markedContentDepth !== 0) {
            throw new PdfException(
                sprintf('Unbalanced marked content: %d open sequence(s) at build time', $this->markedContentDepth)
            );
        }

        $resources = new ResourceDictionary();

        foreach ($this->fontMap as $localName => $font) {
            $resources->addFont($localName, $font);
        }

        foreach ($this->imageMap as $localName => $image) {
            $resources->addImage($localName, $image);
        }

        foreach ($this->formMap as $localName => $form) {
            $resources->addForm($localName, $form);
        }

        foreach ($this->colorSpaceMap as $localName => $cs) {
            $resources->addColorSpace($localName, $cs);
        }

        $operators = implode("\n", $this->operators);

        return new ContentStream($operators, $resources);
    }

    // --- Private ---

    private function registerFont(Font $font): string
    {
        $key = $font->pdfName();

        if (isset($this->fontNameIndex[$key])) {
            return $this->fontNameIndex[$key];
        }

        $localName = 'F' . (++$this->fontCounter);
        $this->fontNameIndex[$key] = $localName;
        $this->fontMap[$localName] = $font;

        return $localName;
    }

    private function registerImage(ImageXObject $image): string
    {
        $id = spl_object_id($image);

        if (isset($this->imageNameIndex[$id])) {
            return $this->imageNameIndex[$id];
        }

        $localName = 'I' . (++$this->imageCounter);
        $this->imageNameIndex[$id] = $localName;
        $this->imageMap[$localName] = $image;

        return $localName;
    }

    private function registerForm(FormXObject $form): string
    {
        $id = spl_object_id($form);

        if (isset($this->formNameIndex[$id])) {
            return $this->formNameIndex[$id];
        }

        $localName = 'X' . (++$this->formCounter);
        $this->formNameIndex[$id] = $localName;
        $this->formMap[$localName] = $form;

        return $localName;
    }

    private function registerColorSpace(ColorSpace $cs): string
    {
        $id = spl_object_id($cs);

        if (isset($this->colorSpaceNameIndex[$id])) {
            return $this->colorSpaceNameIndex[$id];
        }

        $localName = 'CS' . (++$this->colorSpaceCounter);
        $this->colorSpaceNameIndex[$id] = $localName;
        $this->colorSpaceMap[$localName] = $cs;

        return $localName;
    }

    private static function escapeString(string $s): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $s,
        );
    }
}
