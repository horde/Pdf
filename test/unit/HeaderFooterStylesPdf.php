<?php

declare(strict_types=1);

class HeaderFooterStylesPdf extends Horde_Pdf_Writer
{
    public function header()
    {
        $this->setFont('Arial', 'B', 15);
        $w = $this->getStringWidth($this->_info['title']) + 6;
        $this->setX((210 - $w) / 2);
        $this->setDrawColor('rgb', 0 / 255, 80 / 255, 180 / 255);
        $this->setFillColor('rgb', 230 / 255, 230 / 255, 0 / 255);
        $this->setTextColor('rgb', 220 / 255, 50 / 255, 50 / 255);
        $this->setLineWidth(1);
        $this->cell($w, 9, $this->_info['title'], 1, 1, 'C', 1);
        $this->newLine(10);
    }

    public function footer()
    {
        $this->setY(-15);
        $this->setFont('Arial', 'I', 8);
        $this->setTextColor('gray', 128 / 255);
        $this->cell(0, 10, 'Page ' . $this->getPageNo(), 0, 0, 'C');
    }

    public function chapterTitle(int $num, string $label): void
    {
        $this->setFont('Arial', '', 12);
        $this->setFillColor('rgb', 200 / 255, 220 / 255, 255 / 255);
        $this->cell(0, 6, "Chapter $num : $label", 0, 1, 'L', 1);
        $this->newLine(4);
    }

    public function chapterBody(string $file): void
    {
        $filename = __DIR__ . "/fixtures/$file";
        $text = file_get_contents($filename);
        $this->setFont('Times', '', 12);
        $this->multiCell(0, 5, $text);
        $this->newLine();
        $this->setFont('', 'I');
        $this->cell(0, 5, '(end of extract)');
    }

    public function printChapter(int $num, string $title, string $file): void
    {
        $this->addPage();
        $this->chapterTitle($num, $title);
        $this->chapterBody($file);
    }
}
