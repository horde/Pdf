<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class PdfWriter
{
    private DocumentState $state = DocumentState::Initial;
    private float $scaleFactor;

    private float $fwPt;
    private float $fhPt;
    private float $wPt;
    private float $hPt;
    private float $w;
    private float $h;

    private Orientation $defaultOrientation;
    private Orientation $currentOrientation;

    private float $leftMargin;
    private float $topMargin;
    private float $rightMargin;
    private float $breakMargin = 0.0;
    private float $cellMargin;

    private float $x = 0.0;
    private float $y = 0.0;
    private float $lastHeight = 0.0;

    private bool $autoPageBreak = true;
    private float $pageBreakTrigger;

    private ?Font $currentFont = null;
    private float $fontSizePt = 12.0;
    private float $fontSize;
    private string $fontFamily = '';
    private string $fontStyle = '';
    private bool $underline = false;

    private Color $fillColor;
    private Color $textColor;
    private Color $drawColor;
    private bool $colorFlag = false;

    private float $lineWidth;
    private float $wordSpacing = 0.0;

    private int $pageNumber = 0;

    /** @var array<int, string> Raw PDF operator strings per page */
    private array $pageContent = [];

    /** @var array<int, ResourceDictionary> Resources per page */
    private array $pageResources = [];

    /** @var array<int, int> Font counter per page for local names */
    private array $fontCounters = [];

    /** @var array<int, array<string, string>> pdfName → local name per page */
    private array $fontNameMaps = [];

    /** @var array<int, array<string, Font>> localName → Font per page */
    private array $fontMaps = [];

    /** @var array<int, int> Image counter per page */
    private array $imageCounters = [];

    /** @var array<int, array<int, string>> object_id → local name per page */
    private array $imageNameMaps = [];

    /** @var array<int, array<string, ImageXObject>> localName → ImageXObject per page */
    private array $imageMaps = [];

    /** @var array<int, Orientation> */
    private array $pageOrientations = [];

    /** @var array<int, int> ExtGState counter per page */
    private array $gsCounters = [];

    /** @var array<int, array<string, string>> key → local name per page */
    private array $gsKeyMaps = [];

    /** @var array<int, array<string, ExtGState>> localName → ExtGState per page */
    private array $gsMaps = [];

    private bool $inFooter = false;
    private ?HeaderFooterHandler $headerFooter;
    private string $aliasNbPages = '{nb}';
    private bool $compress;

    private ?DocumentInfo $documentInfo = null;
    private ?ViewerPreferences $viewerPreferences = null;

    /** @var array<string, ImageXObject> */
    private array $imageCache = [];

    private int $linkIdCounter = 0;

    /** @var array<int, array{page: int, y: float}> Internal link destinations keyed by link ID */
    private array $internalLinks = [];

    /** @var array<int, array<int, array{x: float, y: float, w: float, h: float, target: int|string}>> Annotations per page */
    private array $pageLinks = [];

    /** @var array<string, array<int, true>> PostScript name → set of codepoints */
    private array $deferredFontChars = [];

    /** @var array<string, DeferredFont> PostScript name → DeferredFont instance */
    private array $deferredFonts = [];

    /** @var array<int, int> Per-page MCID counter */
    private array $mcidCounters = [];

    /** @var array<int, StructureElement> Stack of open structure elements */
    private array $structureStack = [];

    private ?StructureTree $structureTree = null;
    private ?StructureElement $documentElement = null;

    /** @var array<int, array{element: StructureElement, mcid: int, pageNum: int}> */
    private array $deferredMcidBindings = [];

    public function __construct(
        private readonly WriterOptions $options = new WriterOptions(),
        ?HeaderFooterHandler $headerFooter = null,
        bool $compress = true,
        private readonly ?FontResolver $fontResolver = null,
    ) {
        $this->headerFooter = $headerFooter;
        $this->compress = $compress;
        $this->scaleFactor = $options->unit->scaleFactor();

        [$this->fwPt, $this->fhPt] = $options->formatDimensionsInPoints();

        if ($options->orientation === Orientation::Landscape) {
            [$this->fwPt, $this->fhPt] = [$this->fhPt, $this->fwPt];
        }

        $this->wPt = $this->fwPt;
        $this->hPt = $this->fhPt;
        $this->w = $this->wPt / $this->scaleFactor;
        $this->h = $this->hPt / $this->scaleFactor;

        $this->defaultOrientation = $options->orientation;
        $this->currentOrientation = $options->orientation;

        $margin = 28.35 / $this->scaleFactor;
        $this->leftMargin = $margin;
        $this->topMargin = $margin;
        $this->rightMargin = $margin;
        $this->cellMargin = $margin / 10.0;

        $this->lineWidth = 0.567 / $this->scaleFactor;
        $this->pageBreakTrigger = $this->h - $this->breakMargin;

        $this->fillColor = Color::gray(1.0);
        $this->textColor = Color::gray(0.0);
        $this->drawColor = Color::gray(0.0);

        $this->fontSize = $this->fontSizePt / $this->scaleFactor;
    }

    public static function fromLegacy(array $params = []): self
    {
        return new self(WriterOptions::fromLegacy($params));
    }

    // --- Document lifecycle ---

    public function open(): void
    {
        $this->state = DocumentState::Open;
    }

    public function close(): void
    {
        if ($this->state === DocumentState::Closed) {
            return;
        }

        if ($this->pageNumber === 0) {
            $this->addPage();
        }

        $this->finalizePage();
        $this->state = DocumentState::Closed;
    }

    public function addPage(?Orientation $orientation = null): void
    {
        if ($this->state === DocumentState::Initial) {
            $this->open();
        }

        if ($this->pageNumber > 0) {
            $this->finalizePage();
        }

        $this->pageNumber++;
        $orientation ??= $this->defaultOrientation;
        $this->currentOrientation = $orientation;
        $this->pageOrientations[$this->pageNumber] = $orientation;

        if ($orientation !== $this->defaultOrientation) {
            $this->wPt = $this->fhPt;
            $this->hPt = $this->fwPt;
        } else {
            $this->wPt = $this->fwPt;
            $this->hPt = $this->fhPt;
        }
        $this->w = $this->wPt / $this->scaleFactor;
        $this->h = $this->hPt / $this->scaleFactor;
        $this->pageBreakTrigger = $this->h - $this->breakMargin;

        $this->pageContent[$this->pageNumber] = '';
        $this->fontCounters[$this->pageNumber] = 0;
        $this->fontNameMaps[$this->pageNumber] = [];
        $this->fontMaps[$this->pageNumber] = [];
        $this->imageCounters[$this->pageNumber] = 0;
        $this->imageNameMaps[$this->pageNumber] = [];
        $this->imageMaps[$this->pageNumber] = [];
        $this->gsCounters[$this->pageNumber] = 0;
        $this->gsKeyMaps[$this->pageNumber] = [];
        $this->gsMaps[$this->pageNumber] = [];
        $this->mcidCounters[$this->pageNumber] = 0;
        $this->state = DocumentState::PageOpen;

        $this->x = $this->leftMargin;
        $this->y = $this->topMargin;

        $this->out(sprintf('%.2F w', $this->lineWidth * $this->scaleFactor));
        $this->out($this->drawColor->toPdfStrokeString());

        if ($this->currentFont !== null) {
            $localName = $this->registerFont($this->currentFont);
            $this->out(sprintf('BT /%s %.2F Tf ET', $localName, $this->fontSizePt));
        }

        if ($this->headerFooter !== null) {
            $this->headerFooter->writeHeader($this);
        }
    }

    public function getOutput(): string
    {
        if ($this->state !== DocumentState::Closed) {
            $this->close();
        }

        $this->resolveDeferredFonts();

        $totalPages = $this->pageNumber;
        $catalog = new DocumentCatalog();

        /** @var array<int, Page> */
        $pageObjects = [];

        for ($p = 1; $p <= $totalPages; $p++) {
            $operators = str_replace(
                $this->aliasNbPages,
                (string) $totalPages,
                $this->pageContent[$p],
            );

            $resources = new ResourceDictionary();
            foreach ($this->fontMaps[$p] as $localName => $font) {
                $resources->addFont($localName, $font);
            }
            foreach ($this->imageMaps[$p] as $localName => $image) {
                $resources->addImage($localName, $image);
            }
            foreach ($this->gsMaps[$p] as $localName => $gs) {
                $resources->addExtGState($localName, $gs);
            }

            $cs = new ContentStream($operators, $resources);
            $orientation = $this->pageOrientations[$p];
            $mediaBox = $this->mediaBoxForOrientation($orientation);
            $page = new Page($mediaBox);
            $page->addContentStream($cs);
            $pageObjects[$p] = $page;
            $catalog->addPage($page);
        }

        $this->resolveLinks($pageObjects);

        if ($this->documentInfo !== null) {
            $catalog->setInfo($this->documentInfo);
        }
        if ($this->viewerPreferences !== null) {
            $catalog->setViewerPreferences($this->viewerPreferences);
        }
        if (!empty($this->bookmarks)) {
            $catalog->setOutlines($this->buildOutlineTree($pageObjects));
        }
        if ($this->metadata !== null) {
            $catalog->setMetadata($this->metadata);
        }
        foreach ($this->outputIntents as $intent) {
            $catalog->addOutputIntent($intent);
        }
        if ($this->encryption !== null) {
            $catalog->setEncryption($this->encryption);
        }

        if ($this->structureTree !== null) {
            if (!empty($this->structureStack)) {
                throw new PdfException('Unclosed structure elements at output time');
            }
            foreach ($this->deferredMcidBindings as $binding) {
                $page = $pageObjects[$binding['pageNum']];
                $binding['element']->addMarkedContent($binding['mcid'], $page);
            }
            $catalog->setStructureTree($this->structureTree);
        }

        return (new PdfSerializer(compress: $this->compress))->serialize($catalog);
    }

    public function getPageNo(): int
    {
        return $this->pageNumber;
    }

    // --- Margins & layout ---

    public function setMargins(float $left, float $top, ?float $right = null): void
    {
        $this->leftMargin = $left;
        $this->topMargin = $top;
        $this->rightMargin = $right ?? $left;
    }

    public function setLeftMargin(float $margin): void
    {
        $this->leftMargin = $margin;
    }

    public function setTopMargin(float $margin): void
    {
        $this->topMargin = $margin;
    }

    public function setRightMargin(float $margin): void
    {
        $this->rightMargin = $margin;
    }

    public function setAutoPageBreak(bool $auto, float $margin = 0): void
    {
        $this->autoPageBreak = $auto;
        $this->breakMargin = $margin;
        $this->pageBreakTrigger = $this->h - $margin;
    }

    public function getPageWidth(): float
    {
        return $this->w - $this->rightMargin - $this->leftMargin;
    }

    public function getPageHeight(): float
    {
        return $this->h - $this->topMargin - $this->breakMargin;
    }

    public function getDefaultOrientation(): Orientation
    {
        return $this->defaultOrientation;
    }

    public function getFormatWidth(): float
    {
        return $this->fwPt / $this->scaleFactor;
    }

    public function getFormatHeight(): float
    {
        return $this->fhPt / $this->scaleFactor;
    }

    // --- Cursor ---

    public function getX(): float
    {
        return $this->x;
    }

    public function setX(float $x): void
    {
        $this->x = ($x >= 0) ? $x : $this->w + $x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function setY(float $y): void
    {
        $this->x = $this->leftMargin;
        $this->y = ($y >= 0) ? $y : $this->h + $y;
    }

    public function setXY(float $x, float $y): void
    {
        $this->setX($x);
        $this->y = ($y >= 0) ? $y : $this->h + $y;
    }

    public function newLine(float $height = 0): void
    {
        $this->x = $this->leftMargin;
        $this->y += ($height > 0) ? $height : $this->lastHeight;
    }

    // --- Font ---

    public function setFont(string $family, string|FontStyle $style = '', ?float $size = null): void
    {
        if ($family === '') {
            $family = $this->fontFamily;
        }

        $underline = false;

        if ($style instanceof FontStyle) {
            $fontStyle = $style;
            $styleStr = $style->value;
        } else {
            $styleStr = strtoupper($style);
            $underline = str_contains($styleStr, 'U');
            $styleStr = str_replace('U', '', $styleStr);
            if ($styleStr === 'IB') {
                $styleStr = 'BI';
            }
            $fontStyle = FontStyle::tryFrom($styleStr) ?? FontStyle::Regular;
        }

        if ($this->fontResolver !== null) {
            $resolved = $this->fontResolver->resolve($family, $fontStyle);
            $this->currentFont = $resolved;
            $this->fontFamily = strtolower(trim($family));
            $this->fontStyle = $styleStr;
            $this->underline = $underline;

            if ($resolved instanceof DeferredFont) {
                $psName = $resolved->pdfName();
                if (!isset($this->deferredFonts[$psName])) {
                    $this->deferredFonts[$psName] = $resolved;
                    $this->deferredFontChars[$psName] = [];
                }
            }
        } else {
            [$coreFont, $underline] = CoreFont::fromFamilyStyle($family, $styleStr);
            $this->currentFont = $coreFont->toFont();
            $this->fontFamily = $coreFont->family();
            $this->fontStyle = $styleStr;
            $this->underline = $underline;
        }

        if ($size !== null && $size > 0) {
            $this->fontSizePt = $size;
            $this->fontSize = $size / $this->scaleFactor;
        }

        if ($this->pageNumber > 0) {
            $localName = $this->registerFont($this->currentFont);
            $this->out(sprintf('BT /%s %.2F Tf ET', $localName, $this->fontSizePt));
        }
    }

    public function setFontSize(float $size): void
    {
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->scaleFactor;

        if ($this->pageNumber > 0 && $this->currentFont !== null) {
            $localName = $this->registerFont($this->currentFont);
            $this->out(sprintf('BT /%s %.2F Tf ET', $localName, $this->fontSizePt));
        }
    }

    public function getStringWidth(string $text): float
    {
        if ($this->currentFont === null) {
            return 0.0;
        }

        return $this->currentFont->widthOfString($text, $this->fontSizePt) / $this->scaleFactor;
    }

    public function setFontStyle(string $style): void
    {
        $this->setFont($this->fontFamily, $style);
    }

    public function setTrueTypeFont(Type0Font $font, float $size): void
    {
        $this->currentFont = $font;
        $this->fontSizePt = $size;
        $this->fontSize = $size / $this->scaleFactor;

        if ($this->pageNumber > 0) {
            $localName = $this->registerFont($font);
            $this->out(sprintf('BT /%s %.2F Tf ET', $localName, $this->fontSizePt));
        }
    }

    // --- Color ---

    public function setFillColor(Color $color): void
    {
        $this->fillColor = $color;
        $this->colorFlag = ($this->fillColor->toPdfFillString() !== $this->textColor->toPdfFillString());

        if ($this->pageNumber > 0) {
            $this->out($color->toPdfFillString());
        }
    }

    public function getFillColor(): Color
    {
        return $this->fillColor;
    }

    public function setTextColor(Color $color): void
    {
        $this->textColor = $color;
        $this->colorFlag = ($this->fillColor->toPdfFillString() !== $this->textColor->toPdfFillString());
    }

    public function getTextColor(): Color
    {
        return $this->textColor;
    }

    public function setDrawColor(Color $color): void
    {
        $this->drawColor = $color;

        if ($this->pageNumber > 0) {
            $this->out($color->toPdfStrokeString());
        }
    }

    public function getDrawColor(): Color
    {
        return $this->drawColor;
    }

    // --- Drawing ---

    public function setLineWidth(float $width): void
    {
        $this->lineWidth = $width;

        if ($this->pageNumber > 0) {
            $this->out(sprintf('%.2F w', $width * $this->scaleFactor));
        }
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $k = $this->scaleFactor;
        $this->out(sprintf(
            '%.2F %.2F m %.2F %.2F l S',
            $x1 * $k,
            ($this->h - $y1) * $k,
            $x2 * $k,
            ($this->h - $y2) * $k,
        ));
    }

    public function rect(float $x, float $y, float $width, float $height, ShapeStyle $style = ShapeStyle::Draw): void
    {
        $k = $this->scaleFactor;
        $this->out(sprintf(
            '%.2F %.2F %.2F %.2F re %s',
            $x * $k,
            ($this->h - $y) * $k,
            $width * $k,
            -$height * $k,
            $style->pdfOperator(),
        ));
    }

    public function circle(float $x, float $y, float $r, ShapeStyle $style = ShapeStyle::Draw): void
    {
        $k = $this->scaleFactor;
        $xc = $x * $k;
        $yc = ($this->h - $y) * $k;
        $rr = $r * $k;
        $b = $rr * 0.5522847498;

        $this->out(sprintf(
            '%.2F %.2F m'
            . ' %.2F %.2F %.2F %.2F %.2F %.2F c'
            . ' %.2F %.2F %.2F %.2F %.2F %.2F c'
            . ' %.2F %.2F %.2F %.2F %.2F %.2F c'
            . ' %.2F %.2F %.2F %.2F %.2F %.2F c %s',
            $xc - $rr,
            $yc,
            $xc - $rr,
            $yc + $b,
            $xc - $b,
            $yc + $rr,
            $xc,
            $yc + $rr,
            $xc + $b,
            $yc + $rr,
            $xc + $rr,
            $yc + $b,
            $xc + $rr,
            $yc,
            $xc + $rr,
            $yc - $b,
            $xc + $b,
            $yc - $rr,
            $xc,
            $yc - $rr,
            $xc - $b,
            $yc - $rr,
            $xc - $rr,
            $yc - $b,
            $xc - $rr,
            $yc,
            $style->pdfOperator(),
        ));
    }

    // --- Graphics state ---

    public function setAlpha(float $fillAlpha, ?float $strokeAlpha = null): void
    {
        $gs = ExtGState::alpha($fillAlpha, $strokeAlpha);
        $localName = $this->registerExtGState($gs);
        $this->out('/' . $localName . ' gs');
    }

    public function setBlendMode(BlendMode $mode): void
    {
        $gs = ExtGState::blendMode($mode);
        $localName = $this->registerExtGState($gs);
        $this->out('/' . $localName . ' gs');
    }

    public function writeRotated(float $x, float $y, string $text, float $angleDeg): void
    {
        if ($this->currentFont === null) {
            throw new PdfException('No font set');
        }

        $this->trackDeferredCodepoints($text);

        $k = $this->scaleFactor;
        $localName = $this->registerFont($this->currentFont);
        $textString = $this->encodePdfString($text);

        $transform = AffineTransform::translate($x * $k, ($this->h - $y) * $k)
            ->multiply(AffineTransform::rotate($angleDeg));

        $s = 'q ' . $transform->toPdfOperator();
        $s .= sprintf(' BT /%s %.2F Tf 0 0 Td %s Tj ET Q', $localName, $this->fontSizePt, $textString);

        if ($this->colorFlag) {
            $s = 'q ' . $this->textColor->toPdfFillString() . ' ' . $s . ' Q';
        }

        $this->out($s);
    }

    // --- Links ---

    public function addLink(): int
    {
        $this->linkIdCounter++;
        $this->internalLinks[$this->linkIdCounter] = ['page' => 0, 'y' => 0.0];

        return $this->linkIdCounter;
    }

    public function setLink(int $id, float $y = 0, int $page = -1): void
    {
        if ($page === -1) {
            $page = $this->pageNumber;
        }
        $this->internalLinks[$id] = ['page' => $page, 'y' => $y];
    }

    public function link(float $x, float $y, float $w, float $h, int|string $target): void
    {
        $this->pageLinks[$this->pageNumber][] = [
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h,
            'target' => $target,
        ];
    }

    // --- Text output ---

    public function text(float $x, float $y, string $text): void
    {
        if ($this->currentFont === null) {
            throw new PdfException('No font set');
        }

        $this->trackDeferredCodepoints($text);

        $k = $this->scaleFactor;
        $localName = $this->registerFont($this->currentFont);
        $textString = $this->encodePdfString($text);
        $textX = $x * $k;
        $textY = ($this->h - $y) * $k;

        $s = sprintf(
            'BT /%s %.2F Tf %.2F %.2F Td %s Tj ET',
            $localName,
            $this->fontSizePt,
            $textX,
            $textY,
            $textString,
        );

        if ($this->colorFlag) {
            $s = 'q ' . $this->textColor->toPdfFillString() . ' ' . $s . ' Q';
        }

        if ($this->underline) {
            $s .= ' ' . $this->doUnderline($textX, $textY, $text);
        }

        $this->out($s);
    }

    public function cell(
        float $width,
        float $height = 0,
        string $text = '',
        Border|int|string $border = 0,
        CellNextPosition|int $ln = 0,
        TextAlign|string $align = '',
        bool $fill = false,
        string $link = '',
    ): void {
        $k = $this->scaleFactor;

        if ($this->y + $height > $this->pageBreakTrigger
            && !$this->inFooter
            && $this->autoPageBreak
        ) {
            $savedX = $this->x;
            $savedWs = $this->wordSpacing;
            if ($savedWs > 0) {
                $this->wordSpacing = 0;
                $this->out('0 Tw');
            }
            $this->addPage($this->currentOrientation);
            $this->x = $savedX;
            if ($savedWs > 0) {
                $this->wordSpacing = $savedWs;
                $this->out(sprintf('%.3F Tw', $savedWs * $k));
            }
        }

        if ($width == 0) {
            $width = $this->w - $this->rightMargin - $this->x;
        }

        $border = $this->resolveBorder($border);
        $align = $this->resolveAlign($align);
        $ln = $this->resolveLn($ln);

        $s = '';

        if ($fill || $border->isFull()) {
            if ($fill) {
                $op = $border->isFull() ? 'B' : 'f';
            } else {
                $op = 'S';
            }
            $s .= sprintf(
                '%.2F %.2F %.2F %.2F re %s ',
                $this->x * $k,
                ($this->h - $this->y) * $k,
                $width * $k,
                -$height * $k,
                $op,
            );
        }

        if ($border->hasAny() && !$border->isFull()) {
            $x1 = $this->x * $k;
            $y1 = ($this->h - $this->y) * $k;
            $x2 = ($this->x + $width) * $k;
            $y2 = ($this->h - ($this->y + $height)) * $k;

            if ($border->hasLeft()) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x1, $y1, $x1, $y2);
            }
            if ($border->hasTop()) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x1, $y1, $x2, $y1);
            }
            if ($border->hasRight()) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x2, $y1, $x2, $y2);
            }
            if ($border->hasBottom()) {
                $s .= sprintf('%.2F %.2F m %.2F %.2F l S ', $x1, $y2, $x2, $y2);
            }
        }

        if ($text !== '') {
            if ($this->currentFont === null) {
                throw new PdfException('No font set');
            }

            $this->trackDeferredCodepoints($text);

            $dx = match ($align) {
                TextAlign::Right => $width - $this->cellMargin - $this->getStringWidth($text),
                TextAlign::Center => ($width - $this->getStringWidth($text)) / 2,
                default => $this->cellMargin,
            };

            if ($this->colorFlag) {
                $s .= 'q ' . $this->textColor->toPdfFillString() . ' ';
            }

            $localName = $this->registerFont($this->currentFont);
            $textString = $this->encodePdfString($text);
            $textX = ($this->x + $dx) * $k;
            $textY = ($this->h - ($this->y + 0.5 * $height + 0.3 * $this->fontSize)) * $k;
            $s .= sprintf(
                'BT /%s %.2F Tf %.2F %.2F Td %s Tj ET',
                $localName,
                $this->fontSizePt,
                $textX,
                $textY,
                $textString,
            );

            if ($this->underline) {
                $s .= ' ' . $this->doUnderline($textX, $textY, $text);
            }

            if ($this->colorFlag) {
                $s .= ' Q';
            }
        }

        if ($s !== '') {
            $this->out($s);
        }

        $this->lastHeight = $height;

        if ($ln === CellNextPosition::NextLine || $ln === CellNextPosition::Below) {
            $this->y += $height;
            if ($ln === CellNextPosition::NextLine) {
                $this->x = $this->leftMargin;
            }
        } else {
            $this->x += $width;
        }
    }

    public function multiCell(
        float $width,
        float $height,
        string $text,
        Border|int|string $border = 0,
        TextAlign|string $align = 'J',
        bool $fill = false,
    ): void {
        if ($this->currentFont === null) {
            throw new PdfException('No font set');
        }

        $cw = $this->currentFontWidths();

        if ($width == 0) {
            $width = $this->w - $this->rightMargin - $this->x;
        }

        $wmax = ($width - 2 * $this->cellMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', $text);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }

        $resolvedBorder = $this->resolveBorder($border);
        $resolvedAlign = $this->resolveAlign($align);

        $b = Border::none();
        $b2 = Border::none();

        if ($resolvedBorder->hasAny()) {
            if ($resolvedBorder->isFull()) {
                $b = Border::sides(left: true, right: true, top: true);
                $b2 = Border::sides(left: true, right: true);
            } else {
                $b2 = Border::sides(
                    left: $resolvedBorder->hasLeft(),
                    right: $resolvedBorder->hasRight(),
                );
                $b = $resolvedBorder->hasTop()
                    ? Border::sides(left: $b2->hasLeft(), right: $b2->hasRight(), top: true)
                    : $b2;
            }
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $ns = 0;
        $nl = 1;
        $ls = 0;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                if ($this->wordSpacing > 0) {
                    $this->wordSpacing = 0;
                    $this->out('0 Tw');
                }
                $this->cell($width, $height, substr($s, $j, $i - $j), $b, CellNextPosition::Below, $resolvedAlign, $fill);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($resolvedBorder->hasAny() && $nl === 2) {
                    $b = $b2;
                }
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
                $ls = $l;
                $ns++;
            }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                    if ($this->wordSpacing > 0) {
                        $this->wordSpacing = 0;
                        $this->out('0 Tw');
                    }
                    $this->cell($width, $height, substr($s, $j, $i - $j), $b, CellNextPosition::Below, $resolvedAlign, $fill);
                } else {
                    if ($resolvedAlign === TextAlign::Justify) {
                        $this->wordSpacing = ($ns > 1)
                            ? ($wmax - $ls) / 1000 * $this->fontSize / ($ns - 1)
                            : 0;
                        $this->out(sprintf('%.3F Tw', $this->wordSpacing * $this->scaleFactor));
                    }
                    $this->cell($width, $height, substr($s, $j, $sep - $j), $b, CellNextPosition::Below, $resolvedAlign, $fill);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $ns = 0;
                $nl++;
                if ($resolvedBorder->hasAny() && $nl === 2) {
                    $b = $b2;
                }
            } else {
                $i++;
            }
        }

        if ($this->wordSpacing > 0) {
            $this->wordSpacing = 0;
            $this->out('0 Tw');
        }

        if ($resolvedBorder->hasBottom()) {
            $b = Border::sides(
                left: $b->hasLeft(),
                right: $b->hasRight(),
                top: $b->hasTop(),
                bottom: true,
            );
        }
        $this->cell($width, $height, substr($s, $j, $i - $j), $b, CellNextPosition::Below, $resolvedAlign, $fill);
        $this->x = $this->leftMargin;
    }

    public function write(float $height, string $text, string $link = ''): void
    {
        if ($this->currentFont === null) {
            throw new PdfException('No font set');
        }

        $cw = $this->currentFontWidths();
        $width = $this->w - $this->rightMargin - $this->x;
        $wmax = ($width - 2 * $this->cellMargin) * 1000 / $this->fontSize;
        $s = str_replace("\r", '', $text);
        $nb = strlen($s);
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        $ls = 0;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $this->cell($width, $height, substr($s, $j, $i - $j), 0, CellNextPosition::Below, '', false, $link);
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                if ($nl === 1) {
                    $this->x = $this->leftMargin;
                    $width = $this->w - $this->rightMargin - $this->x;
                    $wmax = ($width - 2 * $this->cellMargin) * 1000 / $this->fontSize;
                }
                $nl++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
                $ls = $l;
            }
            $l += $cw[$c] ?? 0;
            if ($l > $wmax) {
                if ($sep === -1) {
                    if ($this->x > $this->leftMargin) {
                        $this->x = $this->leftMargin;
                        $this->y += $height;
                        $width = $this->w - $this->rightMargin - $this->x;
                        $wmax = ($width - 2 * $this->cellMargin) * 1000 / $this->fontSize;
                        $i++;
                        $nl++;
                        continue;
                    }
                    if ($i === $j) {
                        $i++;
                    }
                    $this->cell($width, $height, substr($s, $j, $i - $j), 0, CellNextPosition::Below, '', false, $link);
                } else {
                    $this->cell($width, $height, substr($s, $j, $sep - $j), 0, CellNextPosition::Below, '', false, $link);
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                if ($nl === 1) {
                    $this->x = $this->leftMargin;
                    $width = $this->w - $this->rightMargin - $this->x;
                    $wmax = ($width - 2 * $this->cellMargin) * 1000 / $this->fontSize;
                }
                $nl++;
            } else {
                $i++;
            }
        }

        if ($i !== $j) {
            $this->cell(
                $l / 1000 * $this->fontSize,
                $height,
                substr($s, $j, $i - $j),
                0,
                CellNextPosition::ToRight,
                '',
                false,
                $link,
            );
        }
    }

    // --- Image ---

    public function image(
        string $file,
        float $x,
        float $y,
        float $width = 0,
        float $height = 0,
        string $type = '',
    ): void {
        if ($type === '') {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $type = match ($ext) {
                'jpg', 'jpeg' => 'jpeg',
                'png' => 'png',
                default => throw new PdfException(sprintf('Unsupported image type: %s', $ext)),
            };
        } else {
            $type = strtolower($type);
            if ($type === 'jpg') {
                $type = 'jpeg';
            }
        }

        if (!isset($this->imageCache[$file])) {
            $this->imageCache[$file] = match ($type) {
                'jpeg' => JpegParser::parseFile($file),
                'png' => PngParser::parseFile($file),
                default => throw new PdfException(sprintf('Unsupported image type: %s', $type)),
            };
        }

        $image = $this->imageCache[$file];

        if ($width == 0 && $height == 0) {
            $width = $image->width / $this->scaleFactor;
            $height = $image->height / $this->scaleFactor;
        } elseif ($width == 0) {
            $width = $height * $image->width / $image->height;
        } elseif ($height == 0) {
            $height = $width * $image->height / $image->width;
        }

        $k = $this->scaleFactor;
        $localName = $this->registerImage($image);
        $this->out(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q',
            $width * $k,
            $height * $k,
            $x * $k,
            $this->hPt - ($y * $k) - ($height * $k),
            $localName,
        ));
    }

    // --- Bookmarks ---

    /** @var array<array{title: string, level: int, page: int, y: float}> */
    private array $bookmarks = [];

    public function addBookmark(string $title, int $level = 0, ?float $y = null): void
    {
        $this->bookmarks[] = [
            'title' => $title,
            'level' => $level,
            'page' => $this->pageNumber,
            'y' => $y ?? $this->y,
        ];
    }

    // --- XMP Metadata & Output Intents ---

    private ?MetadataStream $metadata = null;
    /** @var array<OutputIntent> */
    private array $outputIntents = [];

    public function setMetadata(MetadataStream $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function addOutputIntent(OutputIntent $intent): void
    {
        $this->outputIntents[] = $intent;
    }

    // --- Encryption ---

    private ?EncryptionConfig $encryption = null;

    public function setEncryption(EncryptionConfig $config): void
    {
        $this->encryption = $config;
    }

    // --- Metadata ---

    public function aliasNbPages(string $alias = '{nb}'): void
    {
        $this->aliasNbPages = $alias;
    }

    public function setInfo(string $key, string $value): void
    {
        $title = $this->documentInfo?->title;
        $author = $this->documentInfo?->author;
        $subject = $this->documentInfo?->subject;
        $keywords = $this->documentInfo?->keywords;
        $creator = $this->documentInfo?->creator;
        $creationDate = $this->documentInfo?->creationDate;

        match (strtolower($key)) {
            'title' => $title = $value,
            'author' => $author = $value,
            'subject' => $subject = $value,
            'keywords' => $keywords = $value,
            'creator' => $creator = $value,
            'creationdate' => $creationDate = $value,
            default => null,
        };

        $this->documentInfo = new DocumentInfo(
            title: $title,
            author: $author,
            subject: $subject,
            keywords: $keywords,
            creator: $creator,
            creationDate: $creationDate,
        );
    }

    public function setDisplayMode(string $zoom, string $layout = ''): void
    {
        $zoomMode = match (strtolower($zoom)) {
            'fullpage' => ZoomMode::FullPage,
            'fullwidth' => ZoomMode::FullWidth,
            'real' => ZoomMode::Real,
            default => ZoomMode::DefaultMode,
        };

        $layoutMode = match (strtolower($layout)) {
            'single' => LayoutMode::Single,
            'continuous' => LayoutMode::Continuous,
            'two' => LayoutMode::Two,
            default => LayoutMode::DefaultMode,
        };

        $this->viewerPreferences = new ViewerPreferences(
            zoomMode: $zoomMode,
            layoutMode: $layoutMode,
        );
    }

    public function setCompression(bool $compress): void
    {
        $this->compress = $compress;
    }

    // --- Structure / Tagged PDF ---

    public function beginStructure(StructureType $type, ?string $altText = null, ?string $lang = null): void
    {
        if ($this->structureTree === null) {
            $this->structureTree = new StructureTree();
            $this->documentElement = new StructureElement(StructureType::Document);
            $this->structureTree->add($this->documentElement);
        }

        $element = new StructureElement($type, altText: $altText, lang: $lang);

        $parent = !empty($this->structureStack)
            ? $this->structureStack[count($this->structureStack) - 1]
            : $this->documentElement;
        $parent->addChild($element);

        $mcid = $this->mcidCounters[$this->pageNumber]++;
        $this->deferredMcidBindings[] = [
            'element' => $element,
            'mcid' => $mcid,
            'pageNum' => $this->pageNumber,
        ];

        $this->out(sprintf('/%s <</MCID %d>> BDC', $type->value, $mcid));
        $this->structureStack[] = $element;
    }

    public function endStructure(): void
    {
        if (empty($this->structureStack)) {
            throw new PdfException('endStructure called without matching beginStructure');
        }

        array_pop($this->structureStack);
        $this->out('EMC');
    }

    // --- Private helpers ---

    private function out(string $s): void
    {
        $this->pageContent[$this->pageNumber] .= $s . "\n";
    }

    private function finalizePage(): void
    {
        if ($this->headerFooter !== null && !$this->inFooter) {
            $this->inFooter = true;
            $this->headerFooter->writeFooter($this);
            $this->inFooter = false;
        }

        $this->state = DocumentState::Open;
    }

    private function registerFont(Font $font): string
    {
        $p = $this->pageNumber;
        $key = $font->pdfName();

        if (isset($this->fontNameMaps[$p][$key])) {
            return $this->fontNameMaps[$p][$key];
        }

        $this->fontCounters[$p]++;
        $localName = 'F' . $this->fontCounters[$p];
        $this->fontNameMaps[$p][$key] = $localName;
        $this->fontMaps[$p][$localName] = $font;

        return $localName;
    }

    private function registerImage(ImageXObject $image): string
    {
        $p = $this->pageNumber;
        $id = spl_object_id($image);

        if (isset($this->imageNameMaps[$p][$id])) {
            return $this->imageNameMaps[$p][$id];
        }

        $this->imageCounters[$p]++;
        $localName = 'I' . $this->imageCounters[$p];
        $this->imageNameMaps[$p][$id] = $localName;
        $this->imageMaps[$p][$localName] = $image;

        return $localName;
    }

    private function registerExtGState(ExtGState $gs): string
    {
        $p = $this->pageNumber;
        $key = $gs->key();

        if (isset($this->gsKeyMaps[$p][$key])) {
            return $this->gsKeyMaps[$p][$key];
        }

        $this->gsCounters[$p]++;
        $localName = 'GS' . $this->gsCounters[$p];
        $this->gsKeyMaps[$p][$key] = $localName;
        $this->gsMaps[$p][$localName] = $gs;

        return $localName;
    }

    private function mediaBoxForOrientation(Orientation $orientation): Rectangle
    {
        if ($orientation !== $this->defaultOrientation) {
            return Rectangle::fromDimensions($this->fhPt, $this->fwPt);
        }

        return Rectangle::fromDimensions($this->fwPt, $this->fhPt);
    }

    private function resolveBorder(Border|int|string $border): Border
    {
        if ($border instanceof Border) {
            return $border;
        }

        return Border::fromLegacy($border);
    }

    private function resolveAlign(TextAlign|string $align): TextAlign
    {
        if ($align instanceof TextAlign) {
            return $align;
        }

        return match (strtoupper($align)) {
            'L' => TextAlign::Left,
            'C' => TextAlign::Center,
            'R' => TextAlign::Right,
            'J' => TextAlign::Justify,
            default => TextAlign::Left,
        };
    }

    private function resolveLn(CellNextPosition|int $ln): CellNextPosition
    {
        if ($ln instanceof CellNextPosition) {
            return $ln;
        }

        return CellNextPosition::from($ln);
    }

    /**
     * @return array<string, int>
     */
    private function currentFontWidths(): array
    {
        if ($this->currentFont instanceof Type1Font) {
            return $this->currentFont->widths();
        }

        return [];
    }

    private static function escapeString(string $s): string
    {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $s,
        );
    }

    private function encodePdfString(string $text): string
    {
        if ($this->currentFont instanceof Type0Font
            || $this->currentFont instanceof CidFont
            || $this->currentFont instanceof DeferredFont
        ) {
            $encoded = $this->currentFont->encode($text);
            return '<' . strtoupper(bin2hex($encoded)) . '>';
        }
        return '(' . self::escapeString($text) . ')';
    }

    private function trackDeferredCodepoints(string $text): void
    {
        if (!($this->currentFont instanceof DeferredFont)) {
            return;
        }

        $psName = $this->currentFont->pdfName();
        $chars = mb_str_split($text, 1, 'UTF-8');
        foreach ($chars as $char) {
            $cp = mb_ord($char, 'UTF-8');
            $this->deferredFontChars[$psName][$cp] = true;
        }
    }

    private function resolveDeferredFonts(): void
    {
        if (empty($this->deferredFonts)) {
            return;
        }

        $resolved = [];
        foreach ($this->deferredFonts as $psName => $deferred) {
            $codepoints = array_keys($this->deferredFontChars[$psName] ?? []);
            if (empty($codepoints)) {
                continue;
            }
            $resolved[$psName] = new Type0Font($deferred->fontData(), $codepoints);
        }

        for ($p = 1; $p <= $this->pageNumber; $p++) {
            foreach ($this->fontMaps[$p] as $localName => $font) {
                if ($font instanceof DeferredFont) {
                    $psName = $font->pdfName();
                    if (isset($resolved[$psName])) {
                        $this->fontMaps[$p][$localName] = $resolved[$psName];
                    }
                }
            }
        }
    }

    private function doUnderline(float $x, float $y, string $text): string
    {
        $up = -100;
        $ut = 50;
        $w = $this->currentFont->widthOfString($text, $this->fontSizePt);

        return sprintf(
            '%.2F %.2F %.2F %.2F re f',
            $x,
            $y - ($up * $this->fontSizePt / 1000.0),
            $w,
            -($ut * $this->fontSizePt / 1000.0),
        );
    }

    /**
     * @param array<int, Page> $pageObjects
     */
    private function resolveLinks(array $pageObjects): void
    {
        foreach ($this->pageLinks as $p => $links) {
            if (!isset($pageObjects[$p])) {
                continue;
            }
            $page = $pageObjects[$p];
            $k = $this->scaleFactor;
            $hPt = $page->mediaBox->height();

            foreach ($links as $link) {
                $rect = new Rectangle(
                    $link['x'] * $k,
                    $hPt - $link['y'] * $k,
                    ($link['x'] + $link['w']) * $k,
                    $hPt - ($link['y'] + $link['h']) * $k,
                );

                if (is_string($link['target'])) {
                    $annot = new LinkAnnotation($rect, new UriAction($link['target']));
                } else {
                    $dest = $this->internalLinks[$link['target']] ?? null;
                    if ($dest === null || $dest['page'] === 0 || !isset($pageObjects[$dest['page']])) {
                        continue;
                    }
                    $targetPage = $pageObjects[$dest['page']];
                    $targetHPt = $targetPage->mediaBox->height();
                    $destination = new Destination($targetPage, top: $targetHPt - $dest['y'] * $k);
                    $annot = new LinkAnnotation($rect, $destination);
                }

                $page->addAnnotation($annot);
            }
        }
    }

    /**
     * @param array<int, Page> $pageObjects
     */
    private function buildOutlineTree(array $pageObjects): OutlineTree
    {
        $tree = new OutlineTree();
        $k = $this->scaleFactor;

        /** @var array<int, OutlineItem> */
        $stack = [];

        foreach ($this->bookmarks as $bm) {
            $page = $pageObjects[$bm['page']] ?? null;
            $destination = null;
            if ($page !== null) {
                $hPt = $page->mediaBox->height();
                $destination = new Destination($page, top: $hPt - $bm['y'] * $k);
            }

            $item = new OutlineItem($bm['title'], $destination, open: true);
            $level = $bm['level'];

            if ($level === 0) {
                $tree->add($item);
                $stack = [0 => $item];
            } else {
                $parentLevel = $level - 1;
                if (isset($stack[$parentLevel])) {
                    $stack[$parentLevel]->addChild($item);
                } else {
                    $tree->add($item);
                }
                $stack[$level] = $item;
            }
        }

        return $tree;
    }
}
