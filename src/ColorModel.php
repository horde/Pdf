<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum ColorModel: string
{
    case Rgb = 'rgb';
    case Cmyk = 'cmyk';
    case Gray = 'gray';
    case Hex = 'hex';
}
