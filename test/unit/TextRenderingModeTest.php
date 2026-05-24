<?php

declare(strict_types=1);

use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\CoreFont;
use Horde\Pdf\PdfWriter;
use Horde\Pdf\TextRenderingMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TextRenderingMode::class)]
#[CoversClass(ContentStreamBuilder::class)]
#[CoversClass(PdfWriter::class)]
class TextRenderingModeTest extends TestCase
{
    public function testEnumHasEightCases(): void
    {
        $cases = TextRenderingMode::cases();
        $this->assertCount(8, $cases);
    }

    public function testEnumValues(): void
    {
        $this->assertSame(0, TextRenderingMode::Fill->value);
        $this->assertSame(1, TextRenderingMode::Stroke->value);
        $this->assertSame(2, TextRenderingMode::FillStroke->value);
        $this->assertSame(3, TextRenderingMode::Invisible->value);
        $this->assertSame(4, TextRenderingMode::FillClip->value);
        $this->assertSame(5, TextRenderingMode::StrokeClip->value);
        $this->assertSame(6, TextRenderingMode::FillStrokeClip->value);
        $this->assertSame(7, TextRenderingMode::Clip->value);
    }

    public function testContentStreamBuilderEmitsTrOperator(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 12)
            ->setTextRenderingMode(TextRenderingMode::Stroke)
            ->showText('test')
            ->endText()
            ->build();

        $this->assertStringContainsString('1 Tr', $stream->operators);
    }

    public function testContentStreamBuilderInvisibleMode(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 12)
            ->setTextRenderingMode(TextRenderingMode::Invisible)
            ->showText('hidden')
            ->endText()
            ->build();

        $this->assertStringContainsString('3 Tr', $stream->operators);
    }

    public function testContentStreamBuilderAllModes(): void
    {
        foreach (TextRenderingMode::cases() as $mode) {
            $stream = (new ContentStreamBuilder())
                ->setTextRenderingMode($mode)
                ->build();

            $this->assertSame($mode->value . ' Tr', $stream->operators);
        }
    }

    public function testPdfWriterEmitsRenderingMode(): void
    {
        $pdf = new PdfWriter(compress: false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->setTextRenderingMode(TextRenderingMode::FillStroke);
        $pdf->text(10, 10, 'test');

        $output = $pdf->getOutput();

        $this->assertStringContainsString('BT 2 Tr ET', $output);
    }

    public function testPdfWriterInvisibleMode(): void
    {
        $pdf = new PdfWriter(compress: false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->setTextRenderingMode(TextRenderingMode::Invisible);
        $pdf->text(10, 10, 'OCR overlay');

        $output = $pdf->getOutput();

        $this->assertStringContainsString('BT 3 Tr ET', $output);
    }
}
