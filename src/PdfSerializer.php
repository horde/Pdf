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
        $allExtGStates = [];
        $allForms = [];
        $fontObjNums = [];
        $imageObjNums = [];
        $pageResourceFontObjNums = [];
        $pageResourceImageObjNums = [];
        $pageResourceGStateObjNums = [];
        $pageResourceFormObjNums = [];

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

            $pageGStateNums = [];
            foreach ($res->extGraphicsStates() as $localName => $gs) {
                $key = $gs->key();
                if (!isset($allExtGStates[$key])) {
                    $objectNumber++;
                    $allExtGStates[$key] = ['gs' => $gs, 'objNum' => $objectNumber];
                }
                $pageGStateNums[$localName] = $allExtGStates[$key]['objNum'];
            }
            $pageResourceGStateObjNums[$i] = $pageGStateNums;

            $pageFormNums = [];
            foreach ($res->forms() as $localName => $form) {
                $this->collectForm($form, $allForms, $allFonts, $allImages, $allExtGStates, $objectNumber);
                $key = spl_object_id($form);
                $pageFormNums[$localName] = $allForms[$key]['objNum'];
            }
            $pageResourceFormObjNums[$i] = $pageFormNums;
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

        $outlineObjNums = [];
        $outlineRootObjNum = 0;
        $outlines = $catalog->outlines();
        if ($outlines !== null && !$outlines->isEmpty()) {
            $objectNumber++;
            $outlineRootObjNum = $objectNumber;
            $this->allocateOutlineObjNums($outlines->items(), $objectNumber, $outlineObjNums);
        }

        $metadataObjNum = 0;
        $metadata = $catalog->metadata();
        if ($metadata !== null) {
            $objectNumber++;
            $metadataObjNum = $objectNumber;
        }

        $outputIntentObjNums = [];
        $outputIntents = $catalog->outputIntents();
        foreach ($outputIntents as $intent) {
            $objectNumber++;
            $outputIntentObjNums[] = $objectNumber;
        }

        $encryptObjNum = 0;
        $encryption = $catalog->encryption();
        if ($encryption !== null) {
            $objectNumber++;
            $encryptObjNum = $objectNumber;
        }

        $structTreeRootObjNum = 0;
        $markInfoObjNum = 0;
        $parentTreeObjNum = 0;
        /** @var SplObjectStorage<StructureElement, int> */
        $structElemObjNums = new SplObjectStorage();
        /** @var SplObjectStorage<Page, int> */
        $pageStructParentsMap = new SplObjectStorage();
        $structureTree = $catalog->structureTree();

        if ($structureTree !== null && !$structureTree->isEmpty()) {
            foreach ($pages as $i => $page) {
                $page->setStructParents($i);
                $pageStructParentsMap[$page] = $i;
            }

            $objectNumber++;
            $structTreeRootObjNum = $objectNumber;

            $objectNumber++;
            $markInfoObjNum = $objectNumber;

            $objectNumber++;
            $parentTreeObjNum = $objectNumber;

            $this->allocateStructElemObjNums(
                $structureTree->rootElements(),
                $objectNumber,
                $structElemObjNums,
            );
        }

        $totalObjects = $objectNumber;

        // Generate file ID and encryption handler
        $fileId = md5(microtime(true) . random_bytes(16), true);
        $encHandler = null;
        if ($encryption !== null) {
            $encHandler = EncryptionHandler::create($encryption, $fileId);
        }

        // Determine effective PDF version
        $version = $catalog->version;
        if ($encryption !== null) {
            $minVersion = $encryption->algorithm === EncryptionAlgorithm::AES256
                ? PdfVersion::V2_0
                : PdfVersion::V1_6;
            if ($minVersion->value > $version->value) {
                $version = $minVersion;
            }
        }

        $buffer .= $version->header() . "\n";
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

            if ($page->structParents() !== null) {
                $buffer .= "/StructParents " . $page->structParents() . "\n";
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

            if ($encHandler !== null) {
                $streamData = $encHandler->encryptStream($streamData, $streamObjNums[$i], 0);
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
                $this->serializeType0Font($buffer, $offsets, $entry, $encHandler);
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
            $imageData = $image->data;
            if ($encHandler !== null) {
                $imageData = $encHandler->encryptStream($imageData, $objNum, 0);
            }
            $buffer .= "/Length " . strlen($imageData) . ">>\n";
            $buffer .= "stream\n";
            $buffer .= $imageData . "\n";
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
                if ($encHandler !== null) {
                    $palData = $encHandler->encryptStream($palData, $paletteObjNum, 0);
                }
                $palFilter = $usePalCompression ? '/Filter /FlateDecode ' : '';
                $buffer .= '<<' . $palFilter . '/Length ' . strlen($palData) . ">>\n";
                $buffer .= "stream\n";
                $buffer .= $palData . "\n";
                $buffer .= "endstream\n";
                $buffer .= "endobj\n";
            }
        }

        foreach ($allExtGStates as $entry) {
            $gs = $entry['gs'];
            $objNum = $entry['objNum'];
            $offsets[$objNum] = strlen($buffer);
            $buffer .= $objNum . " 0 obj\n";
            $buffer .= "<</Type /ExtGState\n";
            if ($gs->fillAlpha !== null) {
                $buffer .= sprintf("/ca %.3F\n", $gs->fillAlpha);
            }
            if ($gs->strokeAlpha !== null) {
                $buffer .= sprintf("/CA %.3F\n", $gs->strokeAlpha);
            }
            if ($gs->blendMode !== null) {
                $buffer .= '/BM /' . $gs->blendMode->value . "\n";
            }
            if ($gs->overprint !== null) {
                $buffer .= '/OP ' . ($gs->overprint ? 'true' : 'false') . "\n";
                $buffer .= '/op ' . ($gs->overprint ? 'true' : 'false') . "\n";
            }
            $buffer .= ">>\n";
            $buffer .= "endobj\n";
        }

        foreach ($allForms as $entry) {
            $form = $entry['form'];
            $objNum = $entry['objNum'];
            $offsets[$objNum] = strlen($buffer);

            $content = $form->operators;
            $streamData = $this->compress ? @gzcompress($content) : false;
            $useCompression = $streamData !== false && $this->compress;
            if (!$useCompression) {
                $streamData = $content;
            }
            if ($encHandler !== null) {
                $streamData = $encHandler->encryptStream($streamData, $objNum, 0);
            }

            $buffer .= $objNum . " 0 obj\n";
            $buffer .= "<</Type /XObject\n";
            $buffer .= "/Subtype /Form\n";
            $buffer .= "/FormType 1\n";
            $buffer .= "/BBox " . $form->bbox->toPdfArray() . "\n";

            if ($form->matrix !== null) {
                $buffer .= sprintf(
                    "/Matrix [%.4F %.4F %.4F %.4F %.4F %.4F]\n",
                    $form->matrix->a,
                    $form->matrix->b,
                    $form->matrix->c,
                    $form->matrix->d,
                    $form->matrix->e,
                    $form->matrix->f,
                );
            }

            if ($form->group !== null) {
                $buffer .= "/Group <</Type /Group /S /Transparency";
                if ($form->group->colorSpace !== null) {
                    $buffer .= " /CS /" . $form->group->colorSpace->pdfName();
                }
                if ($form->group->isolated) {
                    $buffer .= " /I true";
                }
                if ($form->group->knockout) {
                    $buffer .= " /K true";
                }
                $buffer .= ">>\n";
            }

            if (isset($entry['resourceDictObjNum'])) {
                $buffer .= "/Resources " . $entry['resourceDictObjNum'] . " 0 R\n";
            }

            $filter = $useCompression ? '/Filter /FlateDecode ' : '';
            $buffer .= $filter . "/Length " . strlen($streamData) . ">>\n";
            $buffer .= "stream\n";
            $buffer .= $streamData . "\n";
            $buffer .= "endstream\n";
            $buffer .= "endobj\n";

            if (isset($entry['resourceDictObjNum'])) {
                $resObjNum = $entry['resourceDictObjNum'];
                $offsets[$resObjNum] = strlen($buffer);
                $buffer .= $resObjNum . " 0 obj\n";
                $buffer .= "<</ProcSet [/PDF /Text /ImageB /ImageC /ImageI]\n";

                if (!empty($entry['fontObjNums'])) {
                    $buffer .= "/Font <<";
                    foreach ($entry['fontObjNums'] as $localName => $fObjNum) {
                        $buffer .= " /" . $localName . " " . $fObjNum . " 0 R";
                    }
                    $buffer .= " >>\n";
                }

                $xObjRefs = ($entry['imageObjNums'] ?? []) + ($entry['formObjNums'] ?? []);
                if (!empty($xObjRefs)) {
                    $buffer .= "/XObject <<";
                    foreach ($xObjRefs as $localName => $xObjNum) {
                        $buffer .= " /" . $localName . " " . $xObjNum . " 0 R";
                    }
                    $buffer .= " >>\n";
                }

                if (!empty($entry['gsObjNums'])) {
                    $buffer .= "/ExtGState <<";
                    foreach ($entry['gsObjNums'] as $localName => $gsObjNum) {
                        $buffer .= " /" . $localName . " " . $gsObjNum . " 0 R";
                    }
                    $buffer .= " >>\n";
                }

                $buffer .= ">>\n";
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
            if (!empty($pageResourceImageObjNums[$i]) || !empty($pageResourceFormObjNums[$i])) {
                $buffer .= "/XObject <<";
                foreach ($pageResourceImageObjNums[$i] as $localName => $objNum) {
                    $buffer .= " /" . $localName . " " . $objNum . " 0 R";
                }
                foreach ($pageResourceFormObjNums[$i] as $localName => $objNum) {
                    $buffer .= " /" . $localName . " " . $objNum . " 0 R";
                }
                $buffer .= " >>\n";
            }
            if (!empty($pageResourceGStateObjNums[$i])) {
                $buffer .= "/ExtGState <<";
                foreach ($pageResourceGStateObjNums[$i] as $localName => $objNum) {
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
        $buffer .= "/Producer " . $this->encTextString('Horde PDF', $infoObjNum, $encHandler) . "\n";
        $info = $catalog->info();
        if ($info !== null) {
            if ($info->title !== null) {
                $buffer .= "/Title " . $this->encTextString($info->title, $infoObjNum, $encHandler) . "\n";
            }
            if ($info->author !== null) {
                $buffer .= "/Author " . $this->encTextString($info->author, $infoObjNum, $encHandler) . "\n";
            }
            if ($info->subject !== null) {
                $buffer .= "/Subject " . $this->encTextString($info->subject, $infoObjNum, $encHandler) . "\n";
            }
            if ($info->keywords !== null) {
                $buffer .= "/Keywords " . $this->encTextString($info->keywords, $infoObjNum, $encHandler) . "\n";
            }
            if ($info->creator !== null) {
                $buffer .= "/Creator " . $this->encTextString($info->creator, $infoObjNum, $encHandler) . "\n";
            }
            if ($info->creationDate !== null) {
                $buffer .= "/CreationDate " . $this->encTextString($info->creationDate, $infoObjNum, $encHandler) . "\n";
            }
        }
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        $offsets[$catalogObjNum] = strlen($buffer);
        $buffer .= $catalogObjNum . " 0 obj\n";
        $buffer .= "<</Type /Catalog\n";
        $buffer .= "/Pages " . $pageTreeObjNum . " 0 R\n";
        if ($outlineRootObjNum > 0) {
            $buffer .= "/Outlines " . $outlineRootObjNum . " 0 R\n";
            $buffer .= "/PageMode /UseOutlines\n";
        }
        if ($metadataObjNum > 0) {
            $buffer .= "/Metadata " . $metadataObjNum . " 0 R\n";
        }
        if (!empty($outputIntentObjNums)) {
            $buffer .= '/OutputIntents [';
            foreach ($outputIntentObjNums as $oiNum) {
                $buffer .= $oiNum . ' 0 R ';
            }
            $buffer .= "]\n";
        }
        if ($markInfoObjNum > 0) {
            $buffer .= "/MarkInfo " . $markInfoObjNum . " 0 R\n";
        }
        if ($structTreeRootObjNum > 0) {
            $buffer .= "/StructTreeRoot " . $structTreeRootObjNum . " 0 R\n";
        }
        $this->writeCatalogViewerPrefs($catalog, $buffer, $pages, $pageObjNums);
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        if ($outlineRootObjNum > 0) {
            $this->serializeOutlines($buffer, $offsets, $outlines, $outlineRootObjNum, $outlineObjNums, $objectMap);
        }

        if ($metadataObjNum > 0) {
            $offsets[$metadataObjNum] = strlen($buffer);
            $buffer .= $metadataObjNum . " 0 obj\n";
            $buffer .= "<</Type /Metadata /Subtype /XML /Length " . strlen($metadata->xml) . ">>\n";
            $buffer .= "stream\n";
            $buffer .= $metadata->xml . "\n";
            $buffer .= "endstream\n";
            $buffer .= "endobj\n";
        }

        foreach ($outputIntents as $idx => $intent) {
            $objNum = $outputIntentObjNums[$idx];
            $offsets[$objNum] = strlen($buffer);
            $buffer .= $objNum . " 0 obj\n";
            $buffer .= "<</Type /OutputIntent\n";
            $buffer .= "/S /" . $intent->subtype . "\n";
            $buffer .= "/OutputConditionIdentifier " . self::textString($intent->outputConditionIdentifier) . "\n";
            $buffer .= "/RegistryName " . self::textString($intent->registryName) . "\n";
            $buffer .= "/Info " . self::textString($intent->info) . "\n";
            $buffer .= ">>\n";
            $buffer .= "endobj\n";
        }

        if ($encryptObjNum > 0 && $encHandler !== null) {
            $offsets[$encryptObjNum] = strlen($buffer);
            $buffer .= $encryptObjNum . " 0 obj\n";
            $buffer .= "<</Filter /Standard\n";
            if ($encryption->algorithm === EncryptionAlgorithm::AES128) {
                $buffer .= "/V 4 /R 4 /Length 128\n";
                $buffer .= "/CF <</StdCF <</AuthEvent /DocOpen /CFM /AESV2 /Length 16>>>>\n";
            } else {
                $buffer .= "/V 5 /R 6 /Length 256\n";
                $buffer .= "/CF <</StdCF <</AuthEvent /DocOpen /CFM /AESV3 /Length 32>>>>\n";
            }
            $buffer .= "/StmF /StdCF /StrF /StdCF\n";
            $buffer .= "/O <" . bin2hex($encHandler->ownerHash()) . ">\n";
            $buffer .= "/U <" . bin2hex($encHandler->userHash()) . ">\n";
            if ($encryption->algorithm === EncryptionAlgorithm::AES256) {
                $buffer .= "/OE <" . bin2hex($encHandler->ownerEncKey()) . ">\n";
                $buffer .= "/UE <" . bin2hex($encHandler->userEncKey()) . ">\n";
                $buffer .= "/Perms <" . bin2hex($encHandler->permsEncrypted()) . ">\n";
            }
            $buffer .= "/P " . $encryption->permissionFlags() . "\n";
            $buffer .= ">>\n";
            $buffer .= "endobj\n";
        }

        if ($structTreeRootObjNum > 0 && $structureTree !== null) {
            $this->serializeStructureTree(
                $buffer,
                $offsets,
                $structureTree,
                $structTreeRootObjNum,
                $markInfoObjNum,
                $parentTreeObjNum,
                $structElemObjNums,
                $pageStructParentsMap,
                $objectMap,
                count($pages),
                $encHandler,
            );
        }

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
        if ($encryptObjNum > 0) {
            $buffer .= "/Encrypt " . $encryptObjNum . " 0 R\n";
            $buffer .= "/ID [<" . bin2hex($fileId) . "> <" . bin2hex($fileId) . ">]\n";
        }
        $buffer .= ">>\n";
        $buffer .= "startxref\n";
        $buffer .= $xrefOffset . "\n";
        $buffer .= "%%EOF\n";

        return $buffer;
    }

    /**
     * @param array<int, mixed> $allForms
     * @param array<string, mixed> $allFonts
     * @param array<int, mixed> $allImages
     * @param array<string, mixed> $allExtGStates
     * @param array<int, bool> $visiting
     */
    private function collectForm(
        FormXObject $form,
        array &$allForms,
        array &$allFonts,
        array &$allImages,
        array &$allExtGStates,
        int &$objectNumber,
        array $visiting = [],
    ): void {
        $key = spl_object_id($form);

        if (isset($allForms[$key])) {
            return;
        }

        if (isset($visiting[$key])) {
            throw new PdfException('Circular reference detected in Form XObject nesting');
        }

        $visiting[$key] = true;

        $objectNumber++;
        $allForms[$key] = ['form' => $form, 'objNum' => $objectNumber];

        $res = $form->resources;
        $fontObjNums = [];
        $imageObjNums = [];
        $gsObjNums = [];
        $formObjNums = [];

        foreach ($res->fonts() as $localName => $font) {
            $fKey = $font->pdfName();
            if (!isset($allFonts[$fKey])) {
                $objectNumber++;
                $allFonts[$fKey] = ['font' => $font, 'objNum' => $objectNumber];
                if ($font->requiresEmbedding()) {
                    $objectNumber++;
                    $allFonts[$fKey]['cidFontObjNum'] = $objectNumber;
                    $objectNumber++;
                    $allFonts[$fKey]['fontDescriptorObjNum'] = $objectNumber;
                    $objectNumber++;
                    $allFonts[$fKey]['fontFileObjNum'] = $objectNumber;
                    $objectNumber++;
                    $allFonts[$fKey]['toUnicodeObjNum'] = $objectNumber;
                    $objectNumber++;
                    $allFonts[$fKey]['cidToGidObjNum'] = $objectNumber;
                }
            }
            $fontObjNums[$localName] = $allFonts[$fKey]['objNum'];
        }

        foreach ($res->images() as $localName => $image) {
            $iKey = spl_object_id($image);
            if (!isset($allImages[$iKey])) {
                $objectNumber++;
                $allImages[$iKey] = ['image' => $image, 'objNum' => $objectNumber];
                if ($image->palette !== null) {
                    $objectNumber++;
                    $allImages[$iKey]['paletteObjNum'] = $objectNumber;
                }
            }
            $imageObjNums[$localName] = $allImages[$iKey]['objNum'];
        }

        foreach ($res->extGraphicsStates() as $localName => $gs) {
            $gKey = $gs->key();
            if (!isset($allExtGStates[$gKey])) {
                $objectNumber++;
                $allExtGStates[$gKey] = ['gs' => $gs, 'objNum' => $objectNumber];
            }
            $gsObjNums[$localName] = $allExtGStates[$gKey]['objNum'];
        }

        foreach ($res->forms() as $localName => $nestedForm) {
            $this->collectForm($nestedForm, $allForms, $allFonts, $allImages, $allExtGStates, $objectNumber, $visiting);
            $nKey = spl_object_id($nestedForm);
            $formObjNums[$localName] = $allForms[$nKey]['objNum'];
        }

        if (!$res->isEmpty()) {
            $objectNumber++;
            $allForms[$key]['resourceDictObjNum'] = $objectNumber;
            $allForms[$key]['fontObjNums'] = $fontObjNums;
            $allForms[$key]['imageObjNums'] = $imageObjNums;
            $allForms[$key]['gsObjNums'] = $gsObjNums;
            $allForms[$key]['formObjNums'] = $formObjNums;
        }
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
     */
    private function serializeType0Font(string &$buffer, array &$offsets, array $entry, ?EncryptionHandler $encHandler): void
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
        if ($encHandler !== null) {
            $fontStreamData = $encHandler->encryptStream($fontStreamData, $fontFileObjNum, 0);
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
        if ($encHandler !== null) {
            $toUnicodeStream = $encHandler->encryptStream($toUnicodeStream, $toUnicodeObjNum, 0);
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
        if ($encHandler !== null) {
            $cidToGidStream = $encHandler->encryptStream($cidToGidStream, $cidToGidObjNum, 0);
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

    /**
     * @param array<OutlineItem> $items
     * @param array<int, OutlineItem> $outlineObjNums
     */
    private function allocateOutlineObjNums(array $items, int &$objectNumber, array &$outlineObjNums): void
    {
        foreach ($items as $item) {
            $objectNumber++;
            $outlineObjNums[$objectNumber] = $item;
            if ($item->hasChildren()) {
                $this->allocateOutlineObjNums($item->children(), $objectNumber, $outlineObjNums);
            }
        }
    }

    /**
     * @param array<int, OutlineItem> $outlineObjNums
     * @param SplObjectStorage<object, int> $objectMap
     */
    private function serializeOutlines(
        string &$buffer,
        array &$offsets,
        OutlineTree $outlines,
        int $rootObjNum,
        array $outlineObjNums,
        SplObjectStorage $objectMap,
    ): void {
        $itemToObjNum = new SplObjectStorage();
        foreach ($outlineObjNums as $objNum => $item) {
            $itemToObjNum[$item] = $objNum;
        }

        $topItems = $outlines->items();
        $firstTopObjNum = $itemToObjNum[$topItems[0]];
        $lastTopObjNum = $itemToObjNum[$topItems[count($topItems) - 1]];

        $offsets[$rootObjNum] = strlen($buffer);
        $buffer .= $rootObjNum . " 0 obj\n";
        $buffer .= "<</Type /Outlines\n";
        $buffer .= "/First " . $firstTopObjNum . " 0 R\n";
        $buffer .= "/Last " . $lastTopObjNum . " 0 R\n";
        $buffer .= "/Count " . $outlines->totalCount() . "\n";
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        $this->serializeOutlineItems($buffer, $offsets, $topItems, $rootObjNum, $itemToObjNum, $objectMap);
    }

    /**
     * @param array<OutlineItem> $siblings
     * @param SplObjectStorage<OutlineItem, int> $itemToObjNum
     * @param SplObjectStorage<object, int> $objectMap
     */
    private function serializeOutlineItems(
        string &$buffer,
        array &$offsets,
        array $siblings,
        int $parentObjNum,
        SplObjectStorage $itemToObjNum,
        SplObjectStorage $objectMap,
    ): void {
        $count = count($siblings);
        for ($i = 0; $i < $count; $i++) {
            $item = $siblings[$i];
            $objNum = $itemToObjNum[$item];

            $offsets[$objNum] = strlen($buffer);
            $buffer .= $objNum . " 0 obj\n";
            $buffer .= "<<\n";
            $buffer .= "/Title " . self::textString($item->title) . "\n";
            $buffer .= "/Parent " . $parentObjNum . " 0 R\n";

            if ($i > 0) {
                $buffer .= "/Prev " . $itemToObjNum[$siblings[$i - 1]] . " 0 R\n";
            }
            if ($i < $count - 1) {
                $buffer .= "/Next " . $itemToObjNum[$siblings[$i + 1]] . " 0 R\n";
            }

            if ($item->hasChildren()) {
                $children = $item->children();
                $firstChild = $itemToObjNum[$children[0]];
                $lastChild = $itemToObjNum[$children[count($children) - 1]];
                $buffer .= "/First " . $firstChild . " 0 R\n";
                $buffer .= "/Last " . $lastChild . " 0 R\n";
                $descendantCount = $item->descendantCount();
                $buffer .= "/Count " . ($item->open ? $descendantCount : -$descendantCount) . "\n";
            }

            if ($item->destination !== null) {
                $pageObjNum = $objectMap->contains($item->destination->page)
                    ? $objectMap[$item->destination->page]
                    : 0;
                $buffer .= sprintf(
                    "/Dest [%d 0 R /XYZ 0 %.2F null]\n",
                    $pageObjNum,
                    $item->destination->top,
                );
            }

            $buffer .= ">>\n";
            $buffer .= "endobj\n";

            if ($item->hasChildren()) {
                $this->serializeOutlineItems($buffer, $offsets, $item->children(), $objNum, $itemToObjNum, $objectMap);
            }
        }
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

    private function encTextString(string $s, int $objNum, ?EncryptionHandler $handler): string
    {
        if ($handler === null) {
            return self::textString($s);
        }
        $encrypted = $handler->encryptString($s, $objNum, 0);
        return '<' . bin2hex($encrypted) . '>';
    }

    /**
     * @param array<int, StructureElement> $elements
     * @param SplObjectStorage<StructureElement, int> $structElemObjNums
     */
    private function allocateStructElemObjNums(
        array $elements,
        int &$objectNumber,
        SplObjectStorage $structElemObjNums,
    ): void {
        foreach ($elements as $element) {
            $objectNumber++;
            $structElemObjNums[$element] = $objectNumber;
            if (!empty($element->children())) {
                $this->allocateStructElemObjNums($element->children(), $objectNumber, $structElemObjNums);
            }
        }
    }

    /**
     * @param SplObjectStorage<StructureElement, int> $structElemObjNums
     * @param SplObjectStorage<Page, int> $pageStructParentsMap
     * @param SplObjectStorage<object, int> $objectMap
     */
    private function serializeStructureTree(
        string &$buffer,
        array &$offsets,
        StructureTree $tree,
        int $structTreeRootObjNum,
        int $markInfoObjNum,
        int $parentTreeObjNum,
        SplObjectStorage $structElemObjNums,
        SplObjectStorage $pageStructParentsMap,
        SplObjectStorage $objectMap,
        int $pageCount,
        ?EncryptionHandler $encHandler,
    ): void {
        // MarkInfo
        $offsets[$markInfoObjNum] = strlen($buffer);
        $buffer .= $markInfoObjNum . " 0 obj\n";
        $buffer .= "<</Marked true>>\n";
        $buffer .= "endobj\n";

        // StructTreeRoot
        $offsets[$structTreeRootObjNum] = strlen($buffer);
        $buffer .= $structTreeRootObjNum . " 0 obj\n";
        $buffer .= "<</Type /StructTreeRoot\n";
        $rootElements = $tree->rootElements();
        $buffer .= "/K [";
        foreach ($rootElements as $elem) {
            $buffer .= $structElemObjNums[$elem] . " 0 R ";
        }
        $buffer .= "]\n";
        $buffer .= "/ParentTree " . $parentTreeObjNum . " 0 R\n";
        $buffer .= "/ParentTreeNextKey " . $pageCount . "\n";
        $buffer .= ">>\n";
        $buffer .= "endobj\n";

        // StructElem objects
        $this->serializeStructElements(
            $buffer,
            $offsets,
            $rootElements,
            $structTreeRootObjNum,
            $structElemObjNums,
            $objectMap,
            $encHandler,
        );

        // ParentTree
        $parentTree = $tree->buildParentTree($pageStructParentsMap);
        $offsets[$parentTreeObjNum] = strlen($buffer);
        $buffer .= $parentTreeObjNum . " 0 obj\n";
        $buffer .= "<</Nums [\n";
        for ($i = 0; $i < $pageCount; $i++) {
            $buffer .= $i . " [";
            if (isset($parentTree[$i])) {
                ksort($parentTree[$i]);
                foreach ($parentTree[$i] as $elem) {
                    $buffer .= $structElemObjNums[$elem] . " 0 R ";
                }
            }
            $buffer .= "]\n";
        }
        $buffer .= "]>>\n";
        $buffer .= "endobj\n";
    }

    /**
     * @param array<int, StructureElement> $elements
     * @param SplObjectStorage<StructureElement, int> $structElemObjNums
     * @param SplObjectStorage<object, int> $objectMap
     */
    private function serializeStructElements(
        string &$buffer,
        array &$offsets,
        array $elements,
        int $parentObjNum,
        SplObjectStorage $structElemObjNums,
        SplObjectStorage $objectMap,
        ?EncryptionHandler $encHandler,
    ): void {
        foreach ($elements as $element) {
            $objNum = $structElemObjNums[$element];
            $offsets[$objNum] = strlen($buffer);
            $buffer .= $objNum . " 0 obj\n";
            $buffer .= "<</Type /StructElem\n";
            $buffer .= "/S /" . $element->type->value . "\n";
            $buffer .= "/P " . $parentObjNum . " 0 R\n";

            $children = $element->children();
            $mcids = $element->markedContentIds();

            if (!empty($children) || !empty($mcids)) {
                $buffer .= "/K [";
                foreach ($mcids as $mc) {
                    $buffer .= $mc['mcid'] . " ";
                }
                foreach ($children as $child) {
                    $buffer .= $structElemObjNums[$child] . " 0 R ";
                }
                $buffer .= "]\n";
            }

            if (!empty($mcids)) {
                $pageObjNum = $objectMap[$mcids[0]['page']];
                $buffer .= "/Pg " . $pageObjNum . " 0 R\n";
            }

            if ($element->altText !== null) {
                $buffer .= "/Alt " . $this->encTextString($element->altText, $objNum, $encHandler) . "\n";
            }
            if ($element->actualText !== null) {
                $buffer .= "/ActualText " . $this->encTextString($element->actualText, $objNum, $encHandler) . "\n";
            }
            if ($element->lang !== null) {
                $buffer .= "/Lang " . $this->encTextString($element->lang, $objNum, $encHandler) . "\n";
            }

            $buffer .= ">>\n";
            $buffer .= "endobj\n";

            if (!empty($children)) {
                $this->serializeStructElements(
                    $buffer,
                    $offsets,
                    $children,
                    $objNum,
                    $structElemObjNums,
                    $objectMap,
                    $encHandler,
                );
            }
        }
    }
}
