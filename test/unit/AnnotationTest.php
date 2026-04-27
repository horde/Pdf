<?php

declare(strict_types=1);

use Horde\Pdf\Action;
use Horde\Pdf\Annotation;
use Horde\Pdf\Destination;
use Horde\Pdf\GoToAction;
use Horde\Pdf\LinkAnnotation;
use Horde\Pdf\Page;
use Horde\Pdf\PageFormat;
use Horde\Pdf\Rectangle;
use Horde\Pdf\UriAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriAction::class)]
#[CoversClass(GoToAction::class)]
#[CoversClass(LinkAnnotation::class)]
class AnnotationTest extends TestCase
{
    public function testUriAction(): void
    {
        $action = new UriAction('https://www.horde.org/');
        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('URI', $action->actionType());
        $this->assertSame('https://www.horde.org/', $action->uri);
    }

    public function testGoToAction(): void
    {
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $dest = new Destination($page, top: 700.0);
        $action = new GoToAction($dest);
        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('GoTo', $action->actionType());
        $this->assertSame($dest, $action->destination);
    }

    public function testLinkAnnotationWithUri(): void
    {
        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $action = new UriAction('https://example.com');
        $link = new LinkAnnotation($rect, $action);

        $this->assertInstanceOf(Annotation::class, $link);
        $this->assertSame('Link', $link->subtype());
        $this->assertSame($rect, $link->rect());
        $this->assertSame($action, $link->target);
    }

    public function testLinkAnnotationWithDestination(): void
    {
        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $dest = new Destination($page, top: 500.0);
        $link = new LinkAnnotation($rect, $dest);

        $this->assertInstanceOf(Destination::class, $link->target);
    }

    public function testPageAnnotations(): void
    {
        $page = new Page(Rectangle::fromPageFormat(PageFormat::A4));
        $rect = new Rectangle(72.0, 700.0, 200.0, 720.0);
        $link = new LinkAnnotation($rect, new UriAction('https://horde.org'));
        $page->addAnnotation($link);

        $this->assertCount(1, $page->annotations());
        $this->assertSame($link, $page->annotations()[0]);
    }
}
