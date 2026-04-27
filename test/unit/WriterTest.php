<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HeaderFooterStylesPdf.php';

#[CoversClass(Horde_Pdf_Writer::class)]
class WriterTest extends TestCase
{
    public function testFactoryWithOptions(): void
    {
        $options = ['orientation' => 'L', 'unit' => 'pt', 'format' => 'A3'];
        $pdf = new Horde_Pdf_Writer($options);

        $this->assertEquals('L', $pdf->getDefaultOrientation());
        $this->assertEquals(841.89, $pdf->getFormatHeight());
        $this->assertEquals(1190.55, $pdf->getFormatWidth());
    }

    public function testFactoryWithDefaults(): void
    {
        $pdf = new Horde_Pdf_Writer();

        $this->assertEquals('P', $pdf->getDefaultOrientation());
        $this->assertTrue(abs($pdf->getScale() - 2.8346456692913) < 0.000001);
        $this->assertEquals(841.89, $pdf->getFormatHeight());
        $this->assertEquals(595.28, $pdf->getFormatWidth());
    }

    public function testHelloWorldUncompressed(): void
    {
        $pdf = new Horde_Pdf_Writer(['orientation' => 'P', 'format' => 'A4']);
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->open();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Courier', '', 8);
        $pdf->text(100, 100, 'First page');
        $pdf->setFontSize(20);
        $pdf->text(100, 200, 'HELLO WORLD!');
        $pdf->addPage();
        $pdf->setFont('Arial', 'BI', 12);
        $pdf->text(100, 100, 'Second page');
        $actual = $pdf->getOutput();

        $expected = $this->fixture('hello_world_uncompressed');
        $this->assertEquals($expected, $actual);
    }

    public function testHelloWorldCompressed(): void
    {
        $pdf = new Horde_Pdf_Writer(['orientation' => 'P', 'format' => 'A4']);
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->open();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Courier', '', 8);
        $pdf->text(100, 100, 'First page');
        $pdf->setFontSize(20);
        $pdf->text(100, 200, 'HELLO WORLD!');
        $pdf->addPage();
        $pdf->setFont('Arial', 'BI', 12);
        $pdf->text(100, 100, 'Second page');
        $actual = $pdf->getOutput();

        $expected = $this->fixture('hello_world_compressed');
        $this->assertEquals($expected, $actual);
    }

    public function testAutoBreak(): void
    {
        $pdf = new Horde_Pdf_Writer(['format' => [50, 50], 'unit' => 'pt']);
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->setCompression(false);
        $pdf->setMargins(0, 0);

        $pdf->setAutoPageBreak(true);
        $pdf->open();
        $pdf->addPage();
        $pdf->setFont('Courier', '', 10);
        $pdf->write(10, "Hello\nHello\nHello\nHello\nHello\nHello\nHello\n");
        $actual = $pdf->getOutput();

        $expected = $this->fixture('auto_break');
        $this->assertEquals($expected, $actual);
    }

    public function testChangePage(): void
    {
        $pdf = new Horde_Pdf_Writer(['format' => [80, 80], 'unit' => 'pt']);
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->setCompression(false);
        $pdf->setMargins(0, 0);
        $pdf->open();

        $pdf->addPage();
        $pdf->setFont('Courier', '', 10);
        $pdf->write(10, "Hello");

        $pdf->addPage();

        $pdf->setPage(1);
        $pdf->write(10, "Goodbye");

        $pdf->setPage(2);

        $expected = $this->fixture('change_page');
        $this->assertEquals($expected, $pdf->getOutput());
    }

    public function testTextColor(): void
    {
        $pdf = new Horde_Pdf_Writer();
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->setCompression(false);
        $pdf->open();
        $pdf->addPage();
        $pdf->setFont('Helvetica', 'B', 48);
        $pdf->setDrawColor('rgb', 50, 0, 0);
        $pdf->setTextColor('rgb', 0, 50, 0);
        $pdf->setFillColor('rgb', 0, 0, 50);
        $pdf->cell(0, 50, 'Hello Colors', 1, 0, 'C', 1);
        $actual = $pdf->getOutput();

        $expected = $this->fixture('text_color');
        $this->assertEquals($expected, $actual);
    }

    public function testTextColorUsingHex(): void
    {
        $pdf = new Horde_Pdf_Writer();
        $pdf->setInfo('timestamp', $this->fixtureCreationDate());
        $pdf->setCompression(false);
        $pdf->open();
        $pdf->addPage();
        $pdf->setFont('Helvetica', 'B', 48);

        $pdf->setDrawColor('hex', '#F00');
        $pdf->setTextColor('hex', '#0F0');
        $pdf->setFillColor('hex', '#00F');

        $this->assertEquals('1.000 0.000 0.000 RG', $pdf->getDrawColor());
        $this->assertEquals('0.000 1.000 0.000 rg', $pdf->getTextColor());
        $this->assertEquals('0.000 0.000 1.000 rg', $pdf->getFillColor());
    }

    public function testUnderline(): void
    {
        $pdf = new Horde_Pdf_Writer(['orientation' => 'P', 'format' => 'A4']);
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->open();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', 'U', 12);
        $pdf->write(15, "Underlined\n");
        $pdf->write(15, 'Horde', 'http://www.horde.org');
        $actual = $pdf->getOutput();

        $expected = $this->fixture('underline');
        $this->assertEquals($expected, $actual);
    }

    public function testHeaderFooterStyles(): void
    {
        $pdf = new HeaderFooterStylesPdf([
            'orientation' => 'P',
            'unit' => 'mm',
            'format' => 'A4',
        ]);
        $pdf->setCompression(false);
        $pdf->setInfo('title', '20000 Leagues Under the Seas');
        $pdf->setInfo('author', 'Jules Verne');
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->printChapter(1, 'A RUNAWAY REEF', '20k_c1.txt');
        $pdf->printChapter(2, 'THE PROS AND CONS', '20k_c2.txt');
        $actual = $pdf->getOutput();

        $expected = $this->fixture('header_footer_styles');
        $this->assertEquals($expected, $actual);
    }

    public function testLinks(): void
    {
        $pdf = new Horde_Pdf_Writer(['orientation' => 'P', 'format' => 'A4']);
        $pdf->setInfo('CreationDate', $this->fixtureCreationDate());
        $pdf->open();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', 'U', 12);
        $pdf->write(15, 'Horde', 'http://www.horde.org');
        $pdf->write(15, "\n");
        $link = $pdf->addLink();
        $pdf->write(15, 'here', $link);
        $pdf->addPage();
        $pdf->setLink($link);
        $pdf->image(__DIR__ . '/fixtures/horde-power1.png', 15, 15, 0, 0, '', 'http://pear.horde.org/');
        $actual = $pdf->getOutput();

        $expected = $this->fixture('links');
        $this->assertEquals($expected, $actual);
    }

    public function testCourierStyle(): void
    {
        $this->expectNotToPerformAssertions();
        $pdf = new Horde_Pdf_Writer();
        $pdf->setFont('courier', 'B', 10);
    }

    protected function fixture(string $name): string
    {
        $filename = __DIR__ . "/fixtures/{$name}.pdf";
        $fixture = file_get_contents($filename);

        $this->assertIsString($fixture);
        return $fixture;
    }

    protected function fixtureCreationDate(): string
    {
        return 'D:20071105152947';
    }
}
