<?php

declare(strict_types=1);

use Horde\Pdf\Page;
use Horde\Pdf\Rectangle;
use Horde\Pdf\StructureElement;
use Horde\Pdf\StructureTree;
use Horde\Pdf\StructureType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
#[CoversClass(StructureTree::class)]
class StructureTreeTest extends TestCase
{
    public function testAddAndRetrieve(): void
    {
        $tree = new StructureTree();
        $elem = new StructureElement(StructureType::Document);
        $tree->add($elem);

        $this->assertCount(1, $tree->rootElements());
        $this->assertSame($elem, $tree->rootElements()[0]);
    }

    public function testIsEmpty(): void
    {
        $tree = new StructureTree();
        $this->assertTrue($tree->isEmpty());

        $tree->add(new StructureElement(StructureType::Document));
        $this->assertFalse($tree->isEmpty());
    }

    public function testBuildParentTree(): void
    {
        $page = new Page(Rectangle::fromDimensions(612, 792));

        $doc = new StructureElement(StructureType::Document);
        $p1 = new StructureElement(StructureType::P);
        $p2 = new StructureElement(StructureType::P);

        $p1->addMarkedContent(0, $page);
        $p2->addMarkedContent(1, $page);
        $doc->addChild($p1);
        $doc->addChild($p2);

        $tree = new StructureTree();
        $tree->add($doc);

        /** @var \SplObjectStorage<Page, int> */
        $pageMap = new \SplObjectStorage();
        $pageMap[$page] = 0;

        $parentTree = $tree->buildParentTree($pageMap);

        $this->assertArrayHasKey(0, $parentTree);
        $this->assertSame($p1, $parentTree[0][0]);
        $this->assertSame($p2, $parentTree[0][1]);
    }
}
