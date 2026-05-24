<?php

declare(strict_types=1);

use Horde\Pdf\OutlineItem;
use Horde\Pdf\OutlineTree;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutlineTree::class)]
class OutlineTreeTest extends TestCase
{
    public function testEmptyByDefault(): void
    {
        $tree = new OutlineTree();
        $this->assertTrue($tree->isEmpty());
        $this->assertEmpty($tree->items());
        $this->assertSame(0, $tree->totalCount());
    }

    public function testAddItem(): void
    {
        $tree = new OutlineTree();
        $item = new OutlineItem('Chapter 1');
        $tree->add($item);

        $this->assertFalse($tree->isEmpty());
        $this->assertCount(1, $tree->items());
        $this->assertSame($item, $tree->items()[0]);
    }

    public function testTotalCountFlatItems(): void
    {
        $tree = new OutlineTree();
        $tree->add(new OutlineItem('Chapter 1'));
        $tree->add(new OutlineItem('Chapter 2'));
        $tree->add(new OutlineItem('Chapter 3'));

        $this->assertSame(3, $tree->totalCount());
    }

    public function testTotalCountWithChildren(): void
    {
        $tree = new OutlineTree();

        $ch1 = new OutlineItem('Chapter 1');
        $ch1->addChild(new OutlineItem('Section 1.1'));
        $ch1->addChild(new OutlineItem('Section 1.2'));

        $ch2 = new OutlineItem('Chapter 2');
        $ch2->addChild(new OutlineItem('Section 2.1'));

        $tree->add($ch1);
        $tree->add($ch2);

        // 2 top-level + 2 children of ch1 + 1 child of ch2 = 5
        $this->assertSame(5, $tree->totalCount());
    }
}
