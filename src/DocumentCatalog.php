<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DocumentCatalog
{
    private PageTree $pageTree;
    private ?DocumentInfo $info = null;
    private ?ViewerPreferences $viewerPreferences = null;
    private ?OutlineTree $outlines = null;
    private ?MetadataStream $metadata = null;
    /** @var array<OutputIntent> */
    private array $outputIntents = [];
    private ?EncryptionConfig $encryption = null;

    public function __construct(
        public readonly PdfVersion $version = PdfVersion::V1_7,
    ) {
        $this->pageTree = new PageTree();
    }

    public function addPage(Page $page): void
    {
        $this->pageTree->addPage($page);
    }

    public function setInfo(DocumentInfo $info): void
    {
        $this->info = $info;
    }

    public function setViewerPreferences(ViewerPreferences $prefs): void
    {
        $this->viewerPreferences = $prefs;
    }

    public function setOutlines(OutlineTree $outlines): void
    {
        $this->outlines = $outlines;
    }

    public function pageTree(): PageTree
    {
        return $this->pageTree;
    }

    public function info(): ?DocumentInfo
    {
        return $this->info;
    }

    public function viewerPreferences(): ?ViewerPreferences
    {
        return $this->viewerPreferences;
    }

    public function outlines(): ?OutlineTree
    {
        return $this->outlines;
    }

    public function setMetadata(MetadataStream $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function metadata(): ?MetadataStream
    {
        return $this->metadata;
    }

    public function addOutputIntent(OutputIntent $intent): void
    {
        $this->outputIntents[] = $intent;
    }

    /**
     * @return array<OutputIntent>
     */
    public function outputIntents(): array
    {
        return $this->outputIntents;
    }

    public function setEncryption(EncryptionConfig $encryption): void
    {
        $this->encryption = $encryption;
    }

    public function encryption(): ?EncryptionConfig
    {
        return $this->encryption;
    }
}
