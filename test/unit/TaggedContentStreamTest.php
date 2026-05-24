<?php

declare(strict_types=1);

use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\CoreFont;
use Horde\Pdf\PdfException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentStreamBuilder::class)]
class TaggedContentStreamTest extends TestCase
{
    public function testBeginMarkedContentEmitsBdc(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginMarkedContent('P', 0)
            ->endMarkedContent()
            ->build();

        $this->assertStringContainsString('/P <</MCID 0>> BDC', $stream->operators);
        $this->assertStringContainsString('EMC', $stream->operators);
    }

    public function testBeginMarkedContentSimpleEmitsBmc(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginMarkedContentSimple('Artifact')
            ->endMarkedContent()
            ->build();

        $this->assertStringContainsString('/Artifact BMC', $stream->operators);
        $this->assertStringContainsString('EMC', $stream->operators);
    }

    public function testUnbalancedEndThrows(): void
    {
        $this->expectException(PdfException::class);

        (new ContentStreamBuilder())->endMarkedContent();
    }

    public function testUnclosedMarkedContentThrowsOnBuild(): void
    {
        $this->expectException(PdfException::class);

        (new ContentStreamBuilder())
            ->beginMarkedContent('Span', 1)
            ->build();
    }

    public function testNestedMarkedContent(): void
    {
        $stream = (new ContentStreamBuilder())
            ->beginMarkedContent('Div', 0)
            ->beginMarkedContent('P', 1)
            ->endMarkedContent()
            ->endMarkedContent()
            ->build();

        $this->assertStringContainsString('/Div <</MCID 0>> BDC', $stream->operators);
        $this->assertStringContainsString('/P <</MCID 1>> BDC', $stream->operators);
    }
}
