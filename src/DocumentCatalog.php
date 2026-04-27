<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DocumentCatalog
{
    private PageTree $pageTree;
    private ?DocumentInfo $info = null;
    private ?ViewerPreferences $viewerPreferences = null;

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
}
