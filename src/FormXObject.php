<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class FormXObject
{
    public function __construct(
        public readonly Rectangle $bbox,
        public readonly string $operators,
        public readonly ResourceDictionary $resources,
        public readonly ?AffineTransform $matrix = null,
        public readonly ?TransparencyGroup $group = null,
    ) {}

    public static function create(
        Rectangle $bbox,
        ContentStream $content,
        ?AffineTransform $matrix = null,
        ?TransparencyGroup $group = null,
    ): self {
        return new self(
            bbox: $bbox,
            operators: $content->operators,
            resources: $content->resources,
            matrix: $matrix,
            group: $group,
        );
    }
}
