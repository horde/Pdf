<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum BlendMode: string
{
    case Normal = 'Normal';
    case Multiply = 'Multiply';
    case Screen = 'Screen';
    case Overlay = 'Overlay';
    case Darken = 'Darken';
    case Lighten = 'Lighten';
    case ColorDodge = 'ColorDodge';
    case ColorBurn = 'ColorBurn';
    case HardLight = 'HardLight';
    case SoftLight = 'SoftLight';
    case Difference = 'Difference';
    case Exclusion = 'Exclusion';
}
