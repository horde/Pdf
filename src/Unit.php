<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum Unit: string
{
    case Point = 'pt';
    case Millimeter = 'mm';
    case Centimeter = 'cm';
    case Inch = 'in';

    public function scaleFactor(): float
    {
        return match ($this) {
            self::Point => 1.0,
            self::Millimeter => 72.0 / 25.4,
            self::Centimeter => 72.0 / 2.54,
            self::Inch => 72.0,
        };
    }
}
