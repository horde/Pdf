<?php

declare(strict_types=1);

namespace Horde\Pdf;

use DateTimeInterface;
use DOMDocument;
use DOMElement;

final class XmpBuilder
{
    private const NS_X = 'adobe:ns:meta/';
    private const NS_RDF = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
    private const NS_DC = 'http://purl.org/dc/elements/1.1/';
    private const NS_XMP = 'http://ns.adobe.com/xap/1.0/';
    private const NS_PDF = 'http://ns.adobe.com/pdf/1.3/';
    private const NS_PDFAID = 'http://www.aiim.org/pdfa/ns/id/';
    private const NS_XMPMM = 'http://ns.adobe.com/xap/1.0/mm/';

    public function __construct(
        private readonly ?string $title = null,
        private readonly ?string $creator = null,
        private readonly ?string $description = null,
        private readonly ?string $creatorTool = null,
        private readonly ?DateTimeInterface $createDate = null,
        private readonly ?DateTimeInterface $modifyDate = null,
        private readonly ?string $producer = null,
        private readonly ?string $keywords = null,
        private readonly ?string $documentId = null,
        private readonly ?string $pdfaConformance = null,
        private readonly ?int $pdfaPart = null,
    ) {}

    public function build(): MetadataStream
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $xmpmeta = $doc->createElementNS(self::NS_X, 'x:xmpmeta');
        $doc->appendChild($xmpmeta);

        $rdf = $doc->createElementNS(self::NS_RDF, 'rdf:RDF');
        $xmpmeta->appendChild($rdf);

        $this->addDublinCore($doc, $rdf);
        $this->addXmpBasic($doc, $rdf);
        $this->addPdfProperties($doc, $rdf);
        $this->addPdfaId($doc, $rdf);
        $this->addXmpMM($doc, $rdf);

        $xmlContent = $doc->saveXML($doc->documentElement);

        $xmp = "<?xpacket begin=\"\xEF\xBB\xBF\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n";
        $xmp .= $xmlContent . "\n";
        $xmp .= str_repeat(str_repeat(' ', 100) . "\n", 20);
        $xmp .= '<?xpacket end="w"?>';

        return new MetadataStream($xmp);
    }

    private function addDublinCore(DOMDocument $doc, DOMElement $rdf): void
    {
        if ($this->title === null && $this->creator === null && $this->description === null) {
            return;
        }

        $desc = $doc->createElementNS(self::NS_RDF, 'rdf:Description');
        $desc->setAttributeNS(self::NS_RDF, 'rdf:about', '');
        $desc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:dc', self::NS_DC);
        $rdf->appendChild($desc);

        if ($this->title !== null) {
            $titleEl = $doc->createElementNS(self::NS_DC, 'dc:title');
            $alt = $doc->createElementNS(self::NS_RDF, 'rdf:Alt');
            $li = $doc->createElementNS(self::NS_RDF, 'rdf:li');
            $li->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:lang', 'x-default');
            $li->textContent = $this->title;
            $alt->appendChild($li);
            $titleEl->appendChild($alt);
            $desc->appendChild($titleEl);
        }

        if ($this->creator !== null) {
            $creatorEl = $doc->createElementNS(self::NS_DC, 'dc:creator');
            $seq = $doc->createElementNS(self::NS_RDF, 'rdf:Seq');
            $li = $doc->createElementNS(self::NS_RDF, 'rdf:li');
            $li->textContent = $this->creator;
            $seq->appendChild($li);
            $creatorEl->appendChild($seq);
            $desc->appendChild($creatorEl);
        }

        if ($this->description !== null) {
            $descEl = $doc->createElementNS(self::NS_DC, 'dc:description');
            $alt = $doc->createElementNS(self::NS_RDF, 'rdf:Alt');
            $li = $doc->createElementNS(self::NS_RDF, 'rdf:li');
            $li->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:lang', 'x-default');
            $li->textContent = $this->description;
            $alt->appendChild($li);
            $descEl->appendChild($alt);
            $desc->appendChild($descEl);
        }
    }

    private function addXmpBasic(DOMDocument $doc, DOMElement $rdf): void
    {
        if ($this->creatorTool === null && $this->createDate === null && $this->modifyDate === null) {
            return;
        }

        $desc = $doc->createElementNS(self::NS_RDF, 'rdf:Description');
        $desc->setAttributeNS(self::NS_RDF, 'rdf:about', '');
        $desc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xmp', self::NS_XMP);
        $rdf->appendChild($desc);

        if ($this->creatorTool !== null) {
            $el = $doc->createElementNS(self::NS_XMP, 'xmp:CreatorTool', $this->creatorTool);
            $desc->appendChild($el);
        }
        if ($this->createDate !== null) {
            $el = $doc->createElementNS(self::NS_XMP, 'xmp:CreateDate', $this->createDate->format('Y-m-d\TH:i:sP'));
            $desc->appendChild($el);
        }
        if ($this->modifyDate !== null) {
            $el = $doc->createElementNS(self::NS_XMP, 'xmp:ModifyDate', $this->modifyDate->format('Y-m-d\TH:i:sP'));
            $desc->appendChild($el);
        }
    }

    private function addPdfProperties(DOMDocument $doc, DOMElement $rdf): void
    {
        if ($this->producer === null && $this->keywords === null) {
            return;
        }

        $desc = $doc->createElementNS(self::NS_RDF, 'rdf:Description');
        $desc->setAttributeNS(self::NS_RDF, 'rdf:about', '');
        $desc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pdf', self::NS_PDF);
        $rdf->appendChild($desc);

        if ($this->producer !== null) {
            $el = $doc->createElementNS(self::NS_PDF, 'pdf:Producer', $this->producer);
            $desc->appendChild($el);
        }
        if ($this->keywords !== null) {
            $el = $doc->createElementNS(self::NS_PDF, 'pdf:Keywords', $this->keywords);
            $desc->appendChild($el);
        }
    }

    private function addPdfaId(DOMDocument $doc, DOMElement $rdf): void
    {
        if ($this->pdfaPart === null && $this->pdfaConformance === null) {
            return;
        }

        $desc = $doc->createElementNS(self::NS_RDF, 'rdf:Description');
        $desc->setAttributeNS(self::NS_RDF, 'rdf:about', '');
        $desc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:pdfaid', self::NS_PDFAID);
        $rdf->appendChild($desc);

        if ($this->pdfaPart !== null) {
            $el = $doc->createElementNS(self::NS_PDFAID, 'pdfaid:part', (string) $this->pdfaPart);
            $desc->appendChild($el);
        }
        if ($this->pdfaConformance !== null) {
            $el = $doc->createElementNS(self::NS_PDFAID, 'pdfaid:conformance', $this->pdfaConformance);
            $desc->appendChild($el);
        }
    }

    private function addXmpMM(DOMDocument $doc, DOMElement $rdf): void
    {
        if ($this->documentId === null) {
            return;
        }

        $desc = $doc->createElementNS(self::NS_RDF, 'rdf:Description');
        $desc->setAttributeNS(self::NS_RDF, 'rdf:about', '');
        $desc->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xmpMM', self::NS_XMPMM);
        $rdf->appendChild($desc);

        $el = $doc->createElementNS(self::NS_XMPMM, 'xmpMM:DocumentID', $this->documentId);
        $desc->appendChild($el);
    }
}
