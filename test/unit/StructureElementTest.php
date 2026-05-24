<?php

declare(strict_types=1);

use Horde\Pdf\Page;
use Horde\Pdf\Rectangle;
use Horde\Pdf\StructureElement;
use Horde\Pdf\StructureType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructureElement::class)]
class StructureElementTest extends TestCase
{
    public function testConstruction(): void
    {
        $elem = new StructureElement(StructureType::P, altText: 'A paragraph', lang: 'en');
        $this->assertSame(StructureType::P, $elem->type);
        $this->assertSame('A paragraph', $elem->altText);
        $this->assertSame('en', $elem->lang);
        $this->assertNull($elem->actualText);
    }

    public function testAddChild(): void
    {
        $parent = new StructureElement(StructureType::Document);
        $child = new StructureElement(StructureType::P);
        $parent->addChild($child);

        $this->assertCount(1, $parent->children());
        $this->assertSame($child, $parent->children()[0]);
    }

    public function testAddMarkedContent(): void
    {
        $elem = new StructureElement(StructureType::Span);
        $page = new Page(Rectangle::fromDimensions(612, 792));
        $elem->addMarkedContent(0, $page);

        $mcids = $elem->markedContentIds();
        $this->assertCount(1, $mcids);
        $this->assertSame(0, $mcids[0]['mcid']);
        $this->assertSame($page, $mcids[0]['page']);
    }

    public function testHasChildrenWithElements(): void
    {
        $parent = new StructureElement(StructureType::Div);
        $this->assertFalse($parent->hasChildren());

        $parent->addChild(new StructureElement(StructureType::P));
        $this->assertTrue($parent->hasChildren());
    }

    public function testHasChildrenWithMarkedContent(): void
    {
        $elem = new StructureElement(StructureType::P);
        $this->assertFalse($elem->hasChildren());

        $elem->addMarkedContent(0, new Page(Rectangle::fromDimensions(612, 792)));
        $this->assertTrue($elem->hasChildren());
    }
}
