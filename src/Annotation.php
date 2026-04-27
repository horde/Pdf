<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface Annotation
{
    public function subtype(): string;

    public function rect(): Rectangle;
}
