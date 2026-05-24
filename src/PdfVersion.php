<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum PdfVersion: string
{
    case V1_4 = '1.4';
    case V1_5 = '1.5';
    case V1_6 = '1.6';
    case V1_7 = '1.7';
    case V2_0 = '2.0';

    public function header(): string
    {
        return '%PDF-' . $this->value;
    }
}
