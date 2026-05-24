<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DeviceNColor
{
    /** @var array<float> */
    private array $tints;

    public function __construct(
        private readonly DeviceNColorSpace $colorSpace,
        float ...$tints,
    ) {
        if (count($tints) !== $colorSpace->componentCount()) {
            throw new PdfException(sprintf(
                'Expected %d tint values, got %d',
                $colorSpace->componentCount(),
                count($tints),
            ));
        }
        $this->tints = $tints;
    }

    public function colorSpace(): DeviceNColorSpace
    {
        return $this->colorSpace;
    }

    public function toPdfFillString(string $resourceName): string
    {
        $values = implode(' ', array_map(
            fn(float $v) => sprintf('%.3F', $v),
            $this->tints,
        ));
        return sprintf('/%s cs %s sc', $resourceName, $values);
    }

    public function toPdfStrokeString(string $resourceName): string
    {
        $values = implode(' ', array_map(
            fn(float $v) => sprintf('%.3F', $v),
            $this->tints,
        ));
        return sprintf('/%s CS %s SC', $resourceName, $values);
    }
}
