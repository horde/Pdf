<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum DocumentState: int
{
    case Initial = 0;
    case Open = 1;
    case PageOpen = 2;
    case Closed = 3;
}
