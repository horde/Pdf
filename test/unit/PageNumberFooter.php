<?php

declare(strict_types=1);

use Horde\Pdf\HeaderFooterHandler;
use Horde\Pdf\PdfWriter;

class PageNumberFooter implements HeaderFooterHandler
{
    public int $headerCallCount = 0;
    public int $footerCallCount = 0;

    public function writeHeader(PdfWriter $writer): void
    {
        $this->headerCallCount++;
    }

    public function writeFooter(PdfWriter $writer): void
    {
        $this->footerCallCount++;
        $writer->setY(-30);
        $writer->setFont('Times', '', 10);
        $writer->cell(0, 10, 'Page ' . $writer->getPageNo(), 0, 0, 'C');
    }
}
