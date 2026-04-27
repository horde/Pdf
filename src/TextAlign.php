<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum TextAlign: string
{
    case Left = 'L';
    case Center = 'C';
    case Right = 'R';
    case Justify = 'J';
}
