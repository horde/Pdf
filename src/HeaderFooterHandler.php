<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface HeaderFooterHandler
{
    public function writeHeader(PdfWriter $writer): void;

    public function writeFooter(PdfWriter $writer): void;
}
