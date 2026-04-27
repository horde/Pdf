<?php

declare(strict_types=1);

use Horde\Pdf\Color;
use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\CoreFont;
use Horde\Pdf\LineCap;
use Horde\Pdf\LineDashPattern;
use Horde\Pdf\PdfException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentStreamBuilder::class)]
class ContentStreamBuilderTest extends TestCase
{
    public function testEmptyBuild(): void
    {
        $builder = new ContentStreamBuilder();
        $stream = $builder->build();
        $this->assertSame('', $stream->operators);
        $this->assertTrue($stream->resources->isEmpty());
    }

    public function testMoveTo(): void
    {
        $stream = (new ContentStreamBuilder())
            ->moveTo(72.0, 720.0)
            ->build();
        $this->assertSame('72.00 720.00 m', $stream->operators);
    }

    public function testLineTo(): void
    {
        $stream = (new ContentStreamBuilder())
            ->moveTo(72.0, 720.0)
            ->lineTo(200.0, 720.0)
            ->build();
        $this->assertStringContainsString('200.00 720.00 l', $stream->operators);
    }

    public function testRect(): void
    {
        $stream = (new ContentStreamBuilder())
            ->rect(10.0, 20.0, 100.0, 50.0)
            ->build();
        $this->assertSame('10.00 20.00 100.00 50.00 re', $stream->operators);
    }

    public function testCurveTo(): void
    {
        $stream = (new ContentStreamBuilder())
            ->curveTo(1.0, 2.0, 3.0, 4.0, 5.0, 6.0)
            ->build();
        $this->assertSame('1.00 2.00 3.00 4.00 5.00 6.00 c', $stream->operators);
    }

    public function testClosePath(): void
    {
        $stream = (new ContentStreamBuilder())
            ->closePath()
            ->build();
        $this->assertSame('h', $stream->operators);
    }

    public function testStroke(): void
    {
        $stream = (new ContentStreamBuilder())
            ->moveTo(0.0, 0.0)
            ->lineTo(100.0, 100.0)
            ->stroke()
            ->build();
        $this->assertStringEndsWith('S', $stream->operators);
    }

    public function testFill(): void
    {
        $stream = (new ContentStreamBuilder())
            ->rect(0.0, 0.0, 100.0, 100.0)
            ->fill()
            ->build();
        $this->assertStringEndsWith('f', $stream->operators);
    }

    public function testFillAndStroke(): void
    {
        $stream = (new ContentStreamBuilder())
            ->rect(0.0, 0.0, 100.0, 100.0)
            ->fillAndStroke()
            ->build();
        $this->assertStringEndsWith('B', $stream->operators);
    }

    public function testSetLineWidth(): void
    {
        $stream = (new ContentStreamBuilder())
            ->setLineWidth(0.50)
            ->build();
        $this->assertSame('0.50 w', $stream->operators);
    }

    public function testSetLineCap(): void
    {
        $stream = (new ContentStreamBuilder())
            ->setLineCap(LineCap::Round)
            ->build();
        $this->assertSame('1 J', $stream->operators);
    }

    public function testSetDashPattern(): void
    {
        $stream = (new ContentStreamBuilder())
            ->setDashPattern(new LineDashPattern([3.0, 2.0], 0.0))
            ->build();
        $this->assertSame('[3.00 2.00] 0.00 d', $stream->operators);
    }

    public function testSetFillColor(): void
    {
        $stream = (new ContentStreamBuilder())
            ->setFillColor(Color::rgb(1.0, 0.0, 0.0))
            ->build();
        $this->assertSame('1.000 0.000 0.000 rg', $stream->operators);
    }

    public function testSetStrokeColor(): void
    {
        $stream = (new ContentStreamBuilder())
            ->setStrokeColor(Color::rgb(0.0, 0.0, 1.0))
            ->build();
        $this->assertSame('0.000 0.000 1.000 RG', $stream->operators);
    }

    public function testBeginEndText(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->endText()
            ->build();
        $this->assertSame("BT\nET", $stream->operators);
    }

    public function testSetFont(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 12.0)
            ->endText()
            ->build();
        $this->assertStringContainsString('/F1 12.00 Tf', $stream->operators);
        $this->assertArrayHasKey('F1', $stream->resources->fonts());
        $this->assertSame('Helvetica', $stream->resources->fonts()['F1']->pdfName());
    }

    public function testShowText(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 12.0)
            ->showText('Hello')
            ->endText()
            ->build();
        $this->assertStringContainsString('(Hello) Tj', $stream->operators);
    }

    public function testShowTextEscapesSpecialChars(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 10.0)
            ->showText('Test (parens) and \\backslash')
            ->endText()
            ->build();
        $this->assertStringContainsString('(Test \\(parens\\) and \\\\backslash) Tj', $stream->operators);
    }

    public function testMoveTextPosition(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->moveTextPosition(72.0, 720.0)
            ->endText()
            ->build();
        $this->assertStringContainsString('72.00 720.00 Td', $stream->operators);
    }

    public function testSetCharSpacing(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setCharSpacing(1.50)
            ->endText()
            ->build();
        $this->assertStringContainsString('1.50 Tc', $stream->operators);
    }

    public function testSetWordSpacing(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setWordSpacing(2.00)
            ->endText()
            ->build();
        $this->assertStringContainsString('2.00 Tw', $stream->operators);
    }

    public function testSaveRestore(): void
    {
        $stream = (new ContentStreamBuilder())
            ->save()
            ->setLineWidth(2.0)
            ->restore()
            ->build();
        $lines = explode("\n", $stream->operators);
        $this->assertSame('q', $lines[0]);
        $this->assertSame('Q', $lines[2]);
    }

    public function testClip(): void
    {
        $stream = (new ContentStreamBuilder())
            ->rect(0.0, 0.0, 100.0, 100.0)
            ->clip()
            ->build();
        $this->assertStringContainsString('W n', $stream->operators);
    }

    public function testShowTextOutsideTextBlockThrows(): void
    {
        $this->expectException(PdfException::class);
        (new ContentStreamBuilder())->showText('Hello');
    }

    public function testEndTextOutsideTextBlockThrows(): void
    {
        $this->expectException(PdfException::class);
        (new ContentStreamBuilder())->endText();
    }

    public function testNestedBeginTextThrows(): void
    {
        $this->expectException(PdfException::class);
        (new ContentStreamBuilder())
            ->beginText()
            ->beginText();
    }

    public function testUnclosedTextBlockThrowsOnBuild(): void
    {
        $this->expectException(PdfException::class);
        (new ContentStreamBuilder())
            ->beginText()
            ->build();
    }

    public function testUnbalancedSaveRestoreThrowsOnBuild(): void
    {
        $this->expectException(PdfException::class);
        (new ContentStreamBuilder())
            ->save()
            ->build();
    }

    public function testRestoreWithoutSaveThrows(): void
    {
        $this->expectException(PdfException::class);
        (new ContentStreamBuilder())->restore();
    }

    public function testFontReuse(): void
    {
        $font = CoreFont::Helvetica->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($font, 12.0)
            ->showText('Hello')
            ->setFont($font, 14.0)
            ->showText('World')
            ->endText()
            ->build();
        $this->assertCount(1, $stream->resources->fonts());
        $ops = $stream->operators;
        $this->assertSame(2, substr_count($ops, '/F1'));
    }

    public function testMultipleFonts(): void
    {
        $helvetica = CoreFont::Helvetica->toFont();
        $courier = CoreFont::Courier->toFont();
        $stream = (new ContentStreamBuilder())
            ->beginText()
            ->setFont($helvetica, 12.0)
            ->showText('Hello')
            ->setFont($courier, 12.0)
            ->showText('World')
            ->endText()
            ->build();
        $this->assertCount(2, $stream->resources->fonts());
        $this->assertStringContainsString('/F1', $stream->operators);
        $this->assertStringContainsString('/F2', $stream->operators);
    }
}
