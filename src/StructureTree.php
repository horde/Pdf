<?php

declare(strict_types=1);

namespace Horde\Pdf;

use SplObjectStorage;

final class StructureTree
{
    /** @var array<int, StructureElement> */
    private array $rootElements = [];

    public function add(StructureElement $element): void
    {
        $this->rootElements[] = $element;
    }

    /**
     * @return array<int, StructureElement>
     */
    public function rootElements(): array
    {
        return $this->rootElements;
    }

    public function isEmpty(): bool
    {
        return empty($this->rootElements);
    }

    /**
     * @param SplObjectStorage<Page, int> $pageStructParentsMap
     * @return array<int, array<int, StructureElement>>
     */
    public function buildParentTree(SplObjectStorage $pageStructParentsMap): array
    {
        $parentTree = [];
        $this->walkForParentTree($this->rootElements, $pageStructParentsMap, $parentTree);
        return $parentTree;
    }

    /**
     * @param array<int, StructureElement> $elements
     * @param SplObjectStorage<Page, int> $pageStructParentsMap
     * @param array<int, array<int, StructureElement>> $parentTree
     */
    private function walkForParentTree(
        array $elements,
        SplObjectStorage $pageStructParentsMap,
        array &$parentTree,
    ): void {
        foreach ($elements as $element) {
            foreach ($element->markedContentIds() as $mc) {
                $structParentsKey = $pageStructParentsMap[$mc['page']];
                $parentTree[$structParentsKey][$mc['mcid']] = $element;
            }
            $this->walkForParentTree($element->children(), $pageStructParentsMap, $parentTree);
        }
    }
}
