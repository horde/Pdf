<?php

declare(strict_types=1);

use Horde\Pdf\AffineTransform;
use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\FormXObject;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentStreamBuilder::class)]
class FormXObjectBuilderTest extends TestCase
{
    public function testDrawFormXObjectEmitsDoOperator(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '',
            resources: new ResourceDictionary(),
        );

        $builder = new ContentStreamBuilder();
        $content = $builder->drawFormXObject($form)->build();

        $this->assertStringContainsString('q /X1 Do Q', $content->operators);
    }

    public function testDrawFormXObjectWithTransform(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '',
            resources: new ResourceDictionary(),
        );

        $transform = AffineTransform::translate(72, 700);
        $builder = new ContentStreamBuilder();
        $content = $builder->drawFormXObject($form, $transform)->build();

        $this->assertStringContainsString('cm /X1 Do Q', $content->operators);
        $this->assertStringContainsString('72.0000', $content->operators);
        $this->assertStringContainsString('700.0000', $content->operators);
    }

    public function testMultipleFormsGetUniqueNames(): void
    {
        $form1 = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: 'op1',
            resources: new ResourceDictionary(),
        );
        $form2 = new FormXObject(
            bbox: Rectangle::fromDimensions(200, 100),
            operators: 'op2',
            resources: new ResourceDictionary(),
        );

        $builder = new ContentStreamBuilder();
        $content = $builder
            ->drawFormXObject($form1)
            ->drawFormXObject($form2)
            ->build();

        $this->assertStringContainsString('/X1 Do', $content->operators);
        $this->assertStringContainsString('/X2 Do', $content->operators);
    }

    public function testSameFormInstanceReusesName(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '',
            resources: new ResourceDictionary(),
        );

        $builder = new ContentStreamBuilder();
        $content = $builder
            ->drawFormXObject($form)
            ->drawFormXObject($form)
            ->build();

        $this->assertSame(2, substr_count($content->operators, '/X1 Do'));
        $this->assertStringNotContainsString('/X2', $content->operators);
    }

    public function testFormRegisteredInResources(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '',
            resources: new ResourceDictionary(),
        );

        $builder = new ContentStreamBuilder();
        $content = $builder->drawFormXObject($form)->build();

        $forms = $content->resources->forms();
        $this->assertCount(1, $forms);
        $this->assertArrayHasKey('X1', $forms);
        $this->assertSame($form, $forms['X1']);
    }
}
