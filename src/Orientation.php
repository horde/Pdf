<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum Orientation: string
{
    case Portrait = 'P';
    case Landscape = 'L';
}
