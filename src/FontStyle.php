<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum FontStyle: string
{
    case Regular = '';
    case Bold = 'B';
    case Italic = 'I';
    case BoldItalic = 'BI';
}
