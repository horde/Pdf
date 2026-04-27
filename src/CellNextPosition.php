<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum CellNextPosition: int
{
    case ToRight = 0;
    case NextLine = 1;
    case Below = 2;
}
