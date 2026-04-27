<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum LayoutMode: string
{
    case Single = 'single';
    case Continuous = 'continuous';
    case Two = 'two';
    case DefaultMode = 'default';
}
