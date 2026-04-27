<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class PageTree
{
    /** @var array<int, Page> */
    private array $pages = [];

    public function addPage(Page $page): void
    {
        $this->pages[] = $page;
    }

    /**
     * @return array<int, Page>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    public function count(): int
    {
        return count($this->pages);
    }
}
