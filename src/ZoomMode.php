<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum ZoomMode: string
{
    case FullPage = 'fullpage';
    case FullWidth = 'fullwidth';
    case Real = 'real';
    case DefaultMode = 'default';
}
