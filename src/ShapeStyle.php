<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum ShapeStyle: string
{
    case Draw = 'D';
    case Fill = 'F';
    case DrawAndFill = 'DF';

    public function pdfOperator(): string
    {
        return match ($this) {
            self::Draw => 'S',
            self::Fill => 'f',
            self::DrawAndFill => 'B',
        };
    }
}
