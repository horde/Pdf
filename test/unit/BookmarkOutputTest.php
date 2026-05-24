<?php

declare(strict_types=1);

use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\OutlineItem;
use Horde\Pdf\OutlineTree;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
#[CoversClass(PdfSerializer::class)]
#[CoversClass(OutlineTree::class)]
#[CoversClass(OutlineItem::class)]
#[CoversClass(DocumentCatalog::class)]
class BookmarkOutputTest extends TestCase
{
    public function testBookmarkProducesOutlinesInPdf(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->addBookmark('Chapter 1');
        $pdf->text(10, 20, 'Hello');

        $pdf->addPage();
        $pdf->addBookmark('Chapter 2');
        $pdf->text(10, 20, 'World');

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Type /Outlines', $output);
        $this->assertStringContainsString('/Title (Chapter 1)', $output);
        $this->assertStringContainsString('/Title (Chapter 2)', $output);
        $this->assertStringContainsString('/PageMode /UseOutlines', $output);
    }

    public function testNestedBookmarks(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->addBookmark('Chapter 1', 0);
        $pdf->text(10, 20, 'Chapter content');
        $pdf->addBookmark('Section 1.1', 1);
        $pdf->text(10, 40, 'Section content');

        $pdf->addPage();
        $pdf->addBookmark('Chapter 2', 0);
        $pdf->text(10, 20, 'Another chapter');

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Title (Chapter 1)', $output);
        $this->assertStringContainsString('/Title (Section 1.1)', $output);
        $this->assertStringContainsString('/Title (Chapter 2)', $output);
        $this->assertStringContainsString('/First ', $output);
        $this->assertStringContainsString('/Last ', $output);
        $this->assertStringContainsString('/Parent ', $output);
    }

    public function testBookmarkSiblingLinks(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->addBookmark('First');
        $pdf->addBookmark('Second');
        $pdf->addBookmark('Third');
        $pdf->text(10, 20, 'Content');

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Next ', $output);
        $this->assertStringContainsString('/Prev ', $output);
    }

    public function testBookmarkDestinationFormat(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->addBookmark('At Top', 0, 50.0);
        $pdf->text(10, 50, 'Content');

        $output = $pdf->getOutput();

        $this->assertMatchesRegularExpression('/\/Dest \[\d+ 0 R \/XYZ 0 [\d.]+ null\]/', $output);
    }

    public function testNoBookmarksNoOutlines(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'No bookmarks');

        $output = $pdf->getOutput();

        $this->assertStringNotContainsString('/Type /Outlines', $output);
        $this->assertStringNotContainsString('/PageMode /UseOutlines', $output);
    }

    public function testSpecialCharactersInBookmarkTitle(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->addBookmark('Title (with parens) & backslash\\');
        $pdf->text(10, 20, 'Content');

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Title (Title \\(with parens\\) & backslash\\\\)', $output);
    }
}
