<?php

declare(strict_types=1);

namespace Horde\Pdf;

use SplObjectStorage;

final class PdfSerializer
{
    public function __construct(
        private readonly bool $compress = true,
    ) {}

    public function serialize(DocumentCatalog $catalog): string
    {
        $buffer = '';
        $offsets = [];
        $objectNumber = 0;

        $pageTree = $catalog->pageTree();
        $pages = $pageTree->pages();

        /** @var SplObjectStorage<object, int> */
        $objectMap = new SplObjectStorage();

        $objectNumber++;
        $pageTreeObjNum = $objectNumber;

        $pageObjNums = [];
        $streamObjNums = [];
        foreach ($pages as $i => $page) {
            $objectNumber++;
            $pageObjNums[$i] = $objectNumber;
            $objectNumber++;
            $streamObjNums[$i] = $objectNumber;
            $objectMap[$page] = $pageObjNums[$i];
        }

        $allFonts = [];
        $allImages = [];
        $fontObjNums = [];
        $imageObjNums = [];
        $pageResourceFontObjNums = [];
        $pageResourceImageObjNums = [];

        foreach ($pages as $i => $page) {
            $res = $page->resourceDictionary();
            $pageFontNums = [];
            foreach ($res->fonts() as $localName => $font) {
                $key = $font->pdfName();
                if (!isset($allFonts[$key])) {
                    $objectNumber++;
                    $allFonts[$key] = ['font' => $font, 'objNum' => $objectNumber];
                    if ($font->requiresEmbedding()) {
                        $objectNumber++;
                        $allFonts[$key]['cidFontObjNum'] = $objectNumber;
                        $objectNumber++;
                        $allFonts[$key]['fontDescriptorObjNum'] = $objectNumber;
                        $objectNumber++;
                        $allFonts[$key]['fontFileObjNum'] = $objectNumber;
                        $objectNumber++;
                        $allFonts[$key]['toUnicodeObjNum'] = $objectNumber;
                        $objectNumber++;
                        $allFonts[$key]['cidToGidObjNum'] = $objectNumber;
                    }
                }
                $pageFontNums[$localName] = $allFonts[$key]['objNum'];
            }
            $pageResourceFontObjNums[$i] = $pageFontNums;

            $pageImageNums = [];
            foreach ($res->images() as $localName => $image) {
                $key = spl_object_id($image);
                if (!isset($allImages[$key])) {
                    $objectNumber++;
                    $allImages[$key] = ['image' => $image, 'objNum' => $objectNumber];
                    if ($image->palette !== null) {
                        $objectNumber++;
                        $allImages[$key]['paletteObjNum'] = $objectNumber;
                    }
                }
                $pageImageNums[$localName] = $allImages[$key]['objNum'];
            }
            $pageResourceImageObjNums[$i] = $pageImageNums;
        }

        $resourceDictObjNums = [];
        foreach ($pages as $i => $page) {
            $objectNumber++;
            $resourceDictObjNums[$i] = $objectNumber;
        }

        $objectNumber++;
        $infoObjNum = $objectNumber;

        $objectNumber++;
        $catalogObjNum = $objectNumber;

        $totalObjects = $objectNumber;

        $buffer .= $catalog->version->header() . "\n";
        $buffer .= "%\xE2\xE3\xCF\xD3\n";

        foreach ($pages as $i => $page) {
            $offsets[$pageObjNums[$i]] = strlen($buffer);
            $buffer .= $pageObjNums[$i] . " 0 obj\n";
            $buffer .= "<</Type /Page\n";
            $buffer .= "/Parent " . $pageTreeObjNum . " 0 R\n";
            $buffer .= "/MediaBox " . $page->mediaBox->toPdfArray() . "\n";
            $buffer .= "/Resources " . $resourceDictObjNums[$i] . " 0 R\n";

            $annotations = $page->annotations();
            if (!empty($annotations)) {
                $buffer .= '/Annots [';
                foreach ($annotations as $annot) {
                    $buffer .= $this->serializeAnnotation($annot, $objectMap);
                }
                $buffer .= "]\n";
            }

            $buffer .= "/Contents " . $streamObjNums[$i] . " 0 R>>\n";
            $buffer .= "endobj\n";

            $streams = $page->contentStreams();
            $content = '';
            foreach ($streams as $stream) {
                if ($content !== '') {
                    $content .= "\n";
                }
                $content .= $stream->operators;
            }

            $streamData = $this->compress ? @gzcompress($content) : false;
            $useCompression = $streamData !== false && $this->compress;
            if (!$useCompression) {
                $streamData = $content;
            }

            $offsets[$streamObjNums[$i]] = strlen($buffer);
            $buffer .= $streamObjNums[$i] . " 0 obj\n";
            $filter = $useCompression ? '/Filter /FlateDecode ' : '';
            $buffer .= '<<' . $filter . '/Length ' . strlen($streamData) . ">>\n";
            $buffer .= "stream\n";
            $buffer .= $streamData . "\n";
            $buffer .= "endstream\n";
            $buffer .= "endobj\n";
        }

        foreach ($allFonts as $entry) {
            $font = $entry['font'];
            $objNum = $entry['objNum'];
            $offsets[$objNum] = strlen($buffer);

            if ($font instanceof Type0Font) {
                $this->serializeType0Font($buffer, $offsets, $entry);
            } else {
                $buffer .= $objNum . " 0 obj\n";
                $buffer .= "<</Type /Font\n";
                $buffer .= "/Subtype /Type1\n";
                $buffer .= "/BaseFont /" . $font->pdfName() . "\n";
                $encoding = $font->encoding();
                if ($encoding === FontEncoding::WinAnsi) {
                    $buffer .= "/Encoding /WinAnsiEncoding\n";
                }
                $buffer .= ">>\n";
                $buffer .= "endobj\n";
            }
        }

        foreach ($allImages as $entry) {
            $image = $entry['image'];
            $objNum = $entry['objNum'];
            $offsets[$objNum] = strlen($buffer);
            $buffer .= $objNum . " 0 obj\n";
            $buffer .= "<</Type /XObject\n";
            $buffer .= "/Subtype /Image\n";
            $buffer .= "/Width " . $image->width . "\n";
            $buffer .= "/Height " . $image->height . "\n";

            if ($image->palette !== null) {
                $paletteObjNum = $entry['paletteObjNum'];
                $paletteEntries = (int) (strlen($image->palette) / 3) - 1;
                $buffer .= "/ColorSpace [/Indexed /DeviceRGB " . $paletteEntries . " " . $paletteObjNum . " 0 R]\n";
            } else {
                $buffer .= "/ColorSpace /" . $image->colorSpace->pdfName() . "\n";
                if ($image->colorSpace->pdfName() === 'DeviceCMYK') {
                    $buffer .= "/Decode [1 0 1 0 1 0 1 0]\n";
                }
            }

            $buffer .= "/BitsPerComponent " . $image->bitsPerComponent . "\n";
            $buffer .= "/Filter /" . $image->filter . "\n";
            if ($image->decodeParms !== null) {
                $buffer .= $image->decodeParms . "\n";
            }
            if ($image->transparency !== null) {
                $trns = '';
                foreach ($image->transparency as $t) {
                    $trns .= $t . ' ' . $t . ' ';
                }
                $buffer .= "/Mask [" . $trns . "]\n";
            }
            $buffer .= "/Length " . strlen($image->data) . ">>\n";
            $buffer .= "stream\n";
            $buffer .= $image->data . "\n";
            $buffer .= "endstream\n";
            $buffer .= "endobj\n";

            if ($image->palette !== null) {
                $paletteObjNum = $entry['paletteObjNum'];
                $offsets[$paletteObjNum] = strlen($buffer);
                $buffer .= $paletteObjNum . " 0 obj\n";
                $palData = $this->compress ? @gzcompress($image->palette) : false;
                $usePalCompression = $palData !== false && $this->compress;
                if (!$usePalCompression) {
                    $palData = $image->palette;
                }
                $palFilter = $usePalCompression ? '/Filter /FlateDecode ' : '';
                $buffer .= '<<' . $palFilter . '/Length ' . strlen($palData) . ">>\n";
                $buffer .= "stream\n";
                $buffer .= $palData . "\n";
                $buffer .= "endstream\n";
                $buffer .= "endobj\n";
            }
        }

        foreach ($pages as $i => $page) {
            $offsets[$resourceDictObjNums[$i]] = strlen($buffer);
            $buffer .= $resourceDictObjNums[$i] . " 0 obj\n";
            $buffer .= "<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]\n";
            if (!empty($pageResourceFontObjNums[$i])) {
                $buffer .= "/Font <<";
                foreach ($pageResourceFontObjNums[$i] as $localName => $objNum) {
                    $buffer .= " /" . $localName . " " . $objNum . " 0 R";
                }
                $buffer .= " >>\n";
            }
            if (!empty($pageResourceImageObjNums[$i])) {
                $buffer .= "/XObject <<";
                foreach ($pageResourceImageObjNums[$i] as $localName => $objNum) {
                    $buffer .= " /" . $localName . " " . $objNum . " 0 R";
                }
                $buffer .= " >>\n";
            }
            $buffer .= ">>\n";
            $buffer .= "endobj\n";
        }

        $offsets[$pageTreeObjNum] = strlen($buffer);
        $buffer .= $pageTreeObjNum . " 0 obj\n";
        $buffer .= "<</Type /Pages\n";
        $kids = '/Kids [';
        foreach ($pageObjNums as $num) {
            $kids .= $num . ' 0 R ';
        }
        $buffer .= $kids . "]\n";
        $buffer .= "/Count " . count($pages) . "\n";
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        $offsets[$infoObjNum] = strlen($buffer);
        $buffer .= $infoObjNum . " 0 obj\n";
        $buffer .= "<<\n";
        $buffer .= "/Producer " . self::textString('Horde PDF') . "\n";
        $info = $catalog->info();
        if ($info !== null) {
            if ($info->title !== null) {
                $buffer .= "/Title " . self::textString($info->title) . "\n";
            }
            if ($info->author !== null) {
                $buffer .= "/Author " . self::textString($info->author) . "\n";
            }
            if ($info->subject !== null) {
                $buffer .= "/Subject " . self::textString($info->subject) . "\n";
            }
            if ($info->keywords !== null) {
                $buffer .= "/Keywords " . self::textString($info->keywords) . "\n";
            }
            if ($info->creator !== null) {
                $buffer .= "/Creator " . self::textString($info->creator) . "\n";
            }
            if ($info->creationDate !== null) {
                $buffer .= "/CreationDate " . self::textString($info->creationDate) . "\n";
            }
        }
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        $offsets[$catalogObjNum] = strlen($buffer);
        $buffer .= $catalogObjNum . " 0 obj\n";
        $buffer .= "<</Type /Catalog\n";
        $buffer .= "/Pages " . $pageTreeObjNum . " 0 R\n";
        $this->writeCatalogViewerPrefs($catalog, $buffer, $pages, $pageObjNums);
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        $xrefOffset = strlen($buffer);
        $buffer .= "xref\n";
        $buffer .= "0 " . ($totalObjects + 1) . "\n";
        $buffer .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $totalObjects; $i++) {
            $buffer .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $buffer .= "trailer\n";
        $buffer .= "<<\n";
        $buffer .= "/Size " . ($totalObjects + 1) . "\n";
        $buffer .= "/Root " . $catalogObjNum . " 0 R\n";
        $buffer .= "/Info " . $infoObjNum . " 0 R\n";
        $buffer .= ">>\n";
        $buffer .= "startxref\n";
        $buffer .= $xrefOffset . "\n";
        $buffer .= "%%EOF\n";

        return $buffer;
    }

    /**
     * @param SplObjectStorage<object, int> $objectMap
     */
    private function serializeAnnotation(Annotation $annot, SplObjectStorage $objectMap): string
    {
        $rect = $annot->rect()->toPdfArray();
        $s = '<</Type /Annot /Subtype /' . $annot->subtype() . ' /Rect ' . $rect . ' /Border [0 0 0] ';

        if ($annot instanceof LinkAnnotation) {
            $target = $annot->target;

            if ($target instanceof UriAction) {
                $s .= '/A <</S /URI /URI ' . self::textString($target->uri) . '>>>>';
            } elseif ($target instanceof Destination) {
                $pageObjNum = $objectMap->contains($target->page)
                    ? $objectMap[$target->page]
                    : 0;
                $s .= sprintf(
                    '/Dest [%d 0 R /XYZ %.2F %.2F null]>>',
                    $pageObjNum,
                    $target->left ?? 0,
                    $target->top,
                );
            } elseif ($target instanceof GoToAction) {
                $dest = $target->destination;
                $pageObjNum = $objectMap->offsetExists($dest->page)
                    ? $objectMap[$dest->page]
                    : 0;
                $s .= sprintf(
                    '/Dest [%d 0 R /XYZ %.2F %.2F null]>>',
                    $pageObjNum,
                    $dest->left ?? 0,
                    $dest->top,
                );
            }
        }

        return $s;
    }

    /**
     * @param array<int, Page> $pages
     * @param array<int, int> $pageObjNums
     */
    private function writeCatalogViewerPrefs(
        DocumentCatalog $catalog,
        string &$buffer,
        array $pages,
        array $pageObjNums,
    ): void {
        $prefs = $catalog->viewerPreferences();
        $firstPageRef = !empty($pageObjNums) ? ($pageObjNums[0] . ' 0 R') : '0 0 R';

        if ($prefs === null) {
            return;
        }

        $zoom = $prefs->zoomMode;
        $layout = $prefs->layoutMode;

        if ($zoom === ZoomMode::FullPage) {
            $buffer .= "/OpenAction [" . $firstPageRef . " /Fit]\n";
        } elseif ($zoom === ZoomMode::FullWidth) {
            $buffer .= "/OpenAction [" . $firstPageRef . " /FitH null]\n";
        } elseif ($zoom === ZoomMode::Real) {
            $buffer .= "/OpenAction [" . $firstPageRef . " /XYZ null null 1]\n";
        } elseif ($prefs->zoomPercent !== null) {
            $factor = $prefs->zoomPercent / 100;
            $buffer .= sprintf("/OpenAction [%s /XYZ null null %.2F]\n", $firstPageRef, $factor);
        }

        if ($layout === LayoutMode::Single) {
            $buffer .= "/PageLayout /SinglePage\n";
        } elseif ($layout === LayoutMode::Continuous) {
            $buffer .= "/PageLayout /OneColumn\n";
        } elseif ($layout === LayoutMode::Two) {
            $buffer .= "/PageLayout /TwoColumnLeft\n";
        }
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<int, int> $offsets
     */
    private function serializeType0Font(string &$buffer, array &$offsets, array $entry): void
    {
        $font = $entry['font'];
        $cidFont = $font->descendant();
        $objNum = $entry['objNum'];
        $cidFontObjNum = $entry['cidFontObjNum'];
        $fontDescriptorObjNum = $entry['fontDescriptorObjNum'];
        $fontFileObjNum = $entry['fontFileObjNum'];
        $toUnicodeObjNum = $entry['toUnicodeObjNum'];
        $cidToGidObjNum = $entry['cidToGidObjNum'];

        // Type0 font dictionary
        $buffer .= $objNum . " 0 obj\n";
        $buffer .= "<</Type /Font\n";
        $buffer .= "/Subtype /Type0\n";
        $buffer .= "/BaseFont /" . $font->pdfName() . "\n";
        $buffer .= "/Encoding /Identity-H\n";
        $buffer .= "/DescendantFonts [" . $cidFontObjNum . " 0 R]\n";
        $buffer .= "/ToUnicode " . $toUnicodeObjNum . " 0 R\n";
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        // CIDFont dictionary
        $offsets[$cidFontObjNum] = strlen($buffer);
        $buffer .= $cidFontObjNum . " 0 obj\n";
        $buffer .= "<</Type /Font\n";
        $buffer .= "/Subtype /CIDFontType2\n";
        $buffer .= "/BaseFont /" . $font->pdfName() . "\n";
        $buffer .= "/CIDSystemInfo <</Registry (Adobe) /Ordering (Identity) /Supplement 0>>\n";
        $buffer .= "/FontDescriptor " . $fontDescriptorObjNum . " 0 R\n";
        $buffer .= "/CIDToGIDMap " . $cidToGidObjNum . " 0 R\n";

        $dw = (int) round($cidFont->defaultWidth() * 1000 / $cidFont->fontData()->unitsPerEm);
        $buffer .= "/DW " . $dw . "\n";

        $widths = $cidFont->cidWidths();
        if (!empty($widths)) {
            $buffer .= "/W [";
            foreach ($widths as [$cid, $width]) {
                $buffer .= $cid . " [" . $width . "] ";
            }
            $buffer .= "]\n";
        }

        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        // FontDescriptor
        $metrics = $cidFont->fontDescriptorMetrics();
        $offsets[$fontDescriptorObjNum] = strlen($buffer);
        $buffer .= $fontDescriptorObjNum . " 0 obj\n";
        $buffer .= "<</Type /FontDescriptor\n";
        $buffer .= "/FontName /" . $font->pdfName() . "\n";
        $buffer .= "/Flags " . $metrics['flags'] . "\n";
        $buffer .= sprintf("/FontBBox [%d %d %d %d]\n", ...$metrics['bbox']);
        $buffer .= "/ItalicAngle " . $metrics['italicAngle'] . "\n";
        $buffer .= "/Ascent " . $metrics['ascent'] . "\n";
        $buffer .= "/Descent " . $metrics['descent'] . "\n";
        $buffer .= "/CapHeight " . $metrics['capHeight'] . "\n";
        $buffer .= "/StemV " . $metrics['stemV'] . "\n";
        $buffer .= "/FontFile2 " . $fontFileObjNum . " 0 R\n";
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        // FontFile2 (embedded subset)
        $fontProgram = $cidFont->subsetFontProgram();
        $fontStreamData = $this->compress ? @gzcompress($fontProgram) : false;
        $useFontCompression = $fontStreamData !== false && $this->compress;
        if (!$useFontCompression) {
            $fontStreamData = $fontProgram;
        }

        $offsets[$fontFileObjNum] = strlen($buffer);
        $buffer .= $fontFileObjNum . " 0 obj\n";
        $fontFilter = $useFontCompression ? '/Filter /FlateDecode ' : '';
        $buffer .= '<<' . $fontFilter . '/Length ' . strlen($fontStreamData)
            . ' /Length1 ' . strlen($fontProgram) . ">>\n";
        $buffer .= "stream\n";
        $buffer .= $fontStreamData . "\n";
        $buffer .= "endstream\n";
        $buffer .= "endobj\n";

        // ToUnicode CMap
        $toUnicodeData = $cidFont->toUnicodeCMap();
        $toUnicodeStream = $this->compress ? @gzcompress($toUnicodeData) : false;
        $useToUnicodeCompression = $toUnicodeStream !== false && $this->compress;
        if (!$useToUnicodeCompression) {
            $toUnicodeStream = $toUnicodeData;
        }

        $offsets[$toUnicodeObjNum] = strlen($buffer);
        $buffer .= $toUnicodeObjNum . " 0 obj\n";
        $toUnicodeFilter = $useToUnicodeCompression ? '/Filter /FlateDecode ' : '';
        $buffer .= '<<' . $toUnicodeFilter . '/Length ' . strlen($toUnicodeStream) . ">>\n";
        $buffer .= "stream\n";
        $buffer .= $toUnicodeStream . "\n";
        $buffer .= "endstream\n";
        $buffer .= "endobj\n";

        // CIDToGIDMap
        $cidToGidData = $cidFont->cidToGidMapData();
        $cidToGidStream = $this->compress ? @gzcompress($cidToGidData) : false;
        $useCidToGidCompression = $cidToGidStream !== false && $this->compress;
        if (!$useCidToGidCompression) {
            $cidToGidStream = $cidToGidData;
        }

        $offsets[$cidToGidObjNum] = strlen($buffer);
        $buffer .= $cidToGidObjNum . " 0 obj\n";
        $cidToGidFilter = $useCidToGidCompression ? '/Filter /FlateDecode ' : '';
        $buffer .= '<<' . $cidToGidFilter . '/Length ' . strlen($cidToGidStream) . ">>\n";
        $buffer .= "stream\n";
        $buffer .= $cidToGidStream . "\n";
        $buffer .= "endstream\n";
        $buffer .= "endobj\n";
    }

    private static function escapeString(string $s): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $s,
        );
    }

    private static function textString(string $s): string
    {
        return '(' . self::escapeString($s) . ')';
    }
}
