<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class IccColor
{
    /** @var array<float> */
    private array $components;

    public function __construct(
        private readonly IccBasedColorSpace $colorSpace,
        float ...$components,
    ) {
        if (count($components) !== $colorSpace->componentCount()) {
            throw new PdfException(sprintf(
                'Expected %d color components, got %d',
                $colorSpace->componentCount(),
                count($components),
            ));
        }
        $this->components = $components;
    }

    public function colorSpace(): IccBasedColorSpace
    {
        return $this->colorSpace;
    }

    public function toPdfFillString(string $resourceName): string
    {
        $values = implode(' ', array_map(
            fn(float $v) => sprintf('%.3F', $v),
            $this->components,
        ));
        return sprintf('/%s cs %s sc', $resourceName, $values);
    }

    public function toPdfStrokeString(string $resourceName): string
    {
        $values = implode(' ', array_map(
            fn(float $v) => sprintf('%.3F', $v),
            $this->components,
        ));
        return sprintf('/%s CS %s SC', $resourceName, $values);
    }
}
