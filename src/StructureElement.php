<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class StructureElement
{
    /** @var array<int, self> */
    private array $childElements = [];

    /** @var array<int, array{mcid: int, page: Page}> */
    private array $markedContentIds = [];

    public function __construct(
        public readonly StructureType $type,
        public readonly ?string $altText = null,
        public readonly ?string $actualText = null,
        public readonly ?string $lang = null,
    ) {}

    public function addChild(self $child): void
    {
        $this->childElements[] = $child;
    }

    public function addMarkedContent(int $mcid, Page $page): void
    {
        $this->markedContentIds[] = ['mcid' => $mcid, 'page' => $page];
    }

    /**
     * @return array<int, self>
     */
    public function children(): array
    {
        return $this->childElements;
    }

    /**
     * @return array<int, array{mcid: int, page: Page}>
     */
    public function markedContentIds(): array
    {
        return $this->markedContentIds;
    }

    public function hasChildren(): bool
    {
        return !empty($this->childElements) || !empty($this->markedContentIds);
    }
}
