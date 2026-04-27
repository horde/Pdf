<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum FontEncoding: string
{
    case WinAnsi = 'WinAnsiEncoding';
    case MacRoman = 'MacRomanEncoding';
    case Symbol = 'Symbol';
    case ZapfDingbats = 'ZapfDingbats';
}
