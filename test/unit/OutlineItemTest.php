<?php

declare(strict_types=1);

use Horde\Pdf\Destination;
use Horde\Pdf\OutlineItem;
use Horde\Pdf\Page;
use Horde\Pdf\PageFormat;
use Horde\Pdf\Rectangle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutlineItem::class)]
class OutlineItemTest extends TestCase
{
    public function testConstructionWithTitle(): void
    {
        $item = new OutlineItem('Chapter 1');
        $this->assertSame('Chapter 1', $item->title);
        $this->assertNull($item->destination);
        $this->assertFalse($item->open);
        $this->assertFalse($item->hasChildren());
        $this->assertEmpty($item->children());
    }

    public function testConstructionWithDestination(): void
    {
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $dest = new Destination($page, top: 700.0);
        $item = new OutlineItem('Section', $dest, open: true);

        $this->assertSame($dest, $item->destination);
        $this->assertTrue($item->open);
    }

    public function testAddChild(): void
    {
        $parent = new OutlineItem('Parent');
        $child = new OutlineItem('Child');
        $parent->addChild($child);

        $this->assertTrue($parent->hasChildren());
        $this->assertCount(1, $parent->children());
        $this->assertSame($child, $parent->children()[0]);
    }

    public function testDescendantCountFlat(): void
    {
        $parent = new OutlineItem('Parent');
        $parent->addChild(new OutlineItem('Child 1'));
        $parent->addChild(new OutlineItem('Child 2'));
        $parent->addChild(new OutlineItem('Child 3'));

        $this->assertSame(3, $parent->descendantCount());
    }

    public function testDescendantCountNested(): void
    {
        $root = new OutlineItem('Root');
        $child = new OutlineItem('Child');
        $grandchild = new OutlineItem('Grandchild');

        $child->addChild($grandchild);
        $root->addChild($child);

        $this->assertSame(2, $root->descendantCount());
        $this->assertSame(1, $child->descendantCount());
        $this->assertSame(0, $grandchild->descendantCount());
    }

    public function testDescendantCountDeepTree(): void
    {
        $root = new OutlineItem('Root');
        $a = new OutlineItem('A');
        $b = new OutlineItem('B');
        $a1 = new OutlineItem('A1');
        $a2 = new OutlineItem('A2');
        $b1 = new OutlineItem('B1');

        $a->addChild($a1);
        $a->addChild($a2);
        $b->addChild($b1);
        $root->addChild($a);
        $root->addChild($b);

        $this->assertSame(5, $root->descendantCount());
    }
}
