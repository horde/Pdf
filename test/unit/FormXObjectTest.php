<?php

declare(strict_types=1);

use Horde\Pdf\AffineTransform;
use Horde\Pdf\ContentStream;
use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\FormXObject;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use Horde\Pdf\TransparencyGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormXObject::class)]
class FormXObjectTest extends TestCase
{
    public function testConstructor(): void
    {
        $bbox = Rectangle::fromDimensions(200, 100);
        $resources = new ResourceDictionary();
        $form = new FormXObject(
            bbox: $bbox,
            operators: 'q 1 0 0 rg 0 0 200 100 re f Q',
            resources: $resources,
        );

        $this->assertSame($bbox, $form->bbox);
        $this->assertSame('q 1 0 0 rg 0 0 200 100 re f Q', $form->operators);
        $this->assertSame($resources, $form->resources);
        $this->assertNull($form->matrix);
        $this->assertNull($form->group);
    }

    public function testConstructorWithOptionalParams(): void
    {
        $bbox = Rectangle::fromDimensions(100, 50);
        $resources = new ResourceDictionary();
        $matrix = AffineTransform::translate(10, 20);
        $group = new TransparencyGroup(isolated: true);

        $form = new FormXObject(
            bbox: $bbox,
            operators: 'BT /F1 12 Tf (Hello) Tj ET',
            resources: $resources,
            matrix: $matrix,
            group: $group,
        );

        $this->assertSame($matrix, $form->matrix);
        $this->assertSame($group, $form->group);
    }

    public function testCreateFactory(): void
    {
        $builder = new ContentStreamBuilder();
        $content = $builder
            ->save()
            ->rect(0, 0, 100, 50)
            ->fill()
            ->restore()
            ->build();

        $bbox = Rectangle::fromDimensions(100, 50);
        $form = FormXObject::create($bbox, $content);

        $this->assertSame($content->operators, $form->operators);
        $this->assertSame($content->resources, $form->resources);
        $this->assertNull($form->matrix);
        $this->assertNull($form->group);
    }

    public function testCreateFactoryWithMatrix(): void
    {
        $content = new ContentStream('', new ResourceDictionary());
        $bbox = Rectangle::fromDimensions(50, 50);
        $matrix = AffineTransform::scale(2.0);

        $form = FormXObject::create($bbox, $content, matrix: $matrix);
        $this->assertSame($matrix, $form->matrix);
    }

    public function testCreateFactoryWithGroup(): void
    {
        $content = new ContentStream('', new ResourceDictionary());
        $bbox = Rectangle::fromDimensions(50, 50);
        $group = new TransparencyGroup(isolated: true, knockout: true);

        $form = FormXObject::create($bbox, $content, group: $group);
        $this->assertSame($group, $form->group);
    }
}
