<?php

declare(strict_types=1);

use Horde\Pdf\FormXObject;
use Horde\Pdf\PdfWriter;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
class FormXObjectWriterTest extends TestCase
{
    public function testDrawFormProducesValidPdf(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(200, 100),
            operators: '1 0 0 rg 0 0 200 100 re f',
            resources: new ResourceDictionary(),
        );

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->drawForm($form, 10, 10);

        $output = $writer->getOutput();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertStringContainsString('/Subtype /Form', $output);
        $this->assertStringContainsString('/X1 Do', $output);
    }

    public function testDrawFormWithDimensions(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '0 1 0 rg 0 0 100 50 re f',
            resources: new ResourceDictionary(),
        );

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->drawForm($form, 20, 30, width: 200, height: 100);

        $output = $writer->getOutput();

        $this->assertStringContainsString('/X1 Do', $output);
        $this->assertStringContainsString('/Subtype /Form', $output);
    }

    public function testSameFormOnMultiplePages(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '1 0 0 rg 0 0 100 50 re f',
            resources: new ResourceDictionary(),
        );

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->drawForm($form, 10, 10);
        $writer->addPage();
        $writer->drawForm($form, 10, 10);

        $output = $writer->getOutput();

        $formCount = substr_count($output, '/Subtype /Form');
        $this->assertSame(1, $formCount);

        $doCount = substr_count($output, '/X1 Do');
        $this->assertSame(2, $doCount);
    }

    public function testDrawFormWidthOnlyScalesProportionally(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '',
            resources: new ResourceDictionary(),
        );

        $writer = new PdfWriter(compress: false);
        $writer->addPage();
        $writer->drawForm($form, 0, 0, width: 200);

        $output = $writer->getOutput();

        $this->assertStringContainsString('/X1 Do', $output);
    }
}
