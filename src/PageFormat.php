<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum PageFormat: string
{
    case A3 = 'a3';
    case A4 = 'a4';
    case A5 = 'a5';
    case Letter = 'letter';
    case Legal = 'legal';

    /**
     * @return array{float, float} Width and height in points.
     */
    public function dimensions(): array
    {
        return match ($this) {
            self::A3 => [841.89, 1190.55],
            self::A4 => [595.28, 841.89],
            self::A5 => [420.94, 595.28],
            self::Letter => [612.0, 792.0],
            self::Legal => [612.0, 1008.0],
        };
    }
}
