<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class LinkAnnotation implements Annotation
{
    public function __construct(
        private readonly Rectangle $rectangle,
        public readonly Action|Destination $target,
    ) {}

    public function subtype(): string
    {
        return 'Link';
    }

    public function rect(): Rectangle
    {
        return $this->rectangle;
    }
}
