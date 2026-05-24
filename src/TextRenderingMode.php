<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum TextRenderingMode: int
{
    case Fill = 0;
    case Stroke = 1;
    case FillStroke = 2;
    case Invisible = 3;
    case FillClip = 4;
    case StrokeClip = 5;
    case FillStrokeClip = 6;
    case Clip = 7;
}
