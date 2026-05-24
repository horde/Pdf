<?php

declare(strict_types=1);

use Horde\Pdf\AffineTransform;
use Horde\Pdf\ContentStream;
use Horde\Pdf\DeviceRgb;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\EncryptionAlgorithm;
use Horde\Pdf\EncryptionConfig;
use Horde\Pdf\ExtGState;
use Horde\Pdf\FormXObject;
use Horde\Pdf\Page;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use Horde\Pdf\TransparencyGroup;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfSerializer::class)]
#[CoversClass(FormXObject::class)]
#[CoversClass(TransparencyGroup::class)]
class FormXObjectSerializerTest extends TestCase
{
    private function createMinimalCatalogWithForm(FormXObject $form, string $localName = 'X1'): DocumentCatalog
    {
        $catalog = new DocumentCatalog();
        $resources = new ResourceDictionary();
        $resources->addForm($localName, $form);
        $content = new ContentStream('q /' . $localName . ' Do Q', $resources);
        $page = new Page(Rectangle::fromDimensions(595.28, 841.89));
        $page->addContentStream($content);
        $catalog->addPage($page);
        return $catalog;
    }

    public function testSimpleFormSerialization(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(200, 100),
            operators: '1 0 0 rg 0 0 200 100 re f',
            resources: new ResourceDictionary(),
        );

        $catalog = $this->createMinimalCatalogWithForm($form);
        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $this->assertStringContainsString('/Type /XObject', $output);
        $this->assertStringContainsString('/Subtype /Form', $output);
        $this->assertStringContainsString('/FormType 1', $output);
        $this->assertStringContainsString('/BBox [0.00 0.00 200.00 100.00]', $output);
        $this->assertStringContainsString('1 0 0 rg 0 0 200 100 re f', $output);
    }

    public function testFormWithMatrix(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: 'q Q',
            resources: new ResourceDictionary(),
            matrix: AffineTransform::translate(10, 20),
        );

        $catalog = $this->createMinimalCatalogWithForm($form);
        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $this->assertStringContainsString('/Matrix [1.0000 0.0000 0.0000 1.0000 10.0000 20.0000]', $output);
    }

    public function testFormWithTransparencyGroup(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: 'q Q',
            resources: new ResourceDictionary(),
            group: new TransparencyGroup(
                colorSpace: new DeviceRgb(),
                isolated: true,
                knockout: true,
            ),
        );

        $catalog = $this->createMinimalCatalogWithForm($form);
        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $this->assertStringContainsString('/Group <<', $output);
        $this->assertStringContainsString('/Type /Group', $output);
        $this->assertStringContainsString('/S /Transparency', $output);
        $this->assertStringContainsString('/CS /DeviceRGB', $output);
        $this->assertStringContainsString('/I true', $output);
        $this->assertStringContainsString('/K true', $output);
    }

    public function testFormWithExtGStateResources(): void
    {
        $resources = new ResourceDictionary();
        $resources->addExtGState('GS1', ExtGState::alpha(0.5));

        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '/GS1 gs 0 0 100 50 re f',
            resources: $resources,
        );

        $catalog = $this->createMinimalCatalogWithForm($form);
        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $this->assertStringContainsString('/Subtype /Form', $output);
        $this->assertStringContainsString('/Resources', $output);
        $this->assertStringContainsString('/ExtGState', $output);
    }

    public function testSameFormOnTwoPages(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '1 0 0 rg 0 0 100 50 re f',
            resources: new ResourceDictionary(),
        );

        $catalog = new DocumentCatalog();

        $resources1 = new ResourceDictionary();
        $resources1->addForm('X1', $form);
        $page1 = new Page(Rectangle::fromDimensions(595.28, 841.89));
        $page1->addContentStream(new ContentStream('q /X1 Do Q', $resources1));
        $catalog->addPage($page1);

        $resources2 = new ResourceDictionary();
        $resources2->addForm('X1', $form);
        $page2 = new Page(Rectangle::fromDimensions(595.28, 841.89));
        $page2->addContentStream(new ContentStream('q /X1 Do Q', $resources2));
        $catalog->addPage($page2);

        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $formCount = substr_count($output, '/Subtype /Form');
        $this->assertSame(1, $formCount);
    }

    public function testNestedForms(): void
    {
        $innerForm = new FormXObject(
            bbox: Rectangle::fromDimensions(50, 25),
            operators: '0 0 1 rg 0 0 50 25 re f',
            resources: new ResourceDictionary(),
        );

        $outerResources = new ResourceDictionary();
        $outerResources->addForm('X1', $innerForm);

        $outerForm = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: 'q /X1 Do Q',
            resources: $outerResources,
        );

        $catalog = $this->createMinimalCatalogWithForm($outerForm);
        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $formCount = substr_count($output, '/Subtype /Form');
        $this->assertSame(2, $formCount);
    }

    public function testFormStreamEncrypted(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '1 0 0 rg 0 0 100 50 re f',
            resources: new ResourceDictionary(),
        );

        $catalog = $this->createMinimalCatalogWithForm($form);
        $catalog->setEncryption(new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            ownerPassword: 'owner',
            userPassword: 'user',
        ));

        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $this->assertStringContainsString('/Subtype /Form', $output);
        $this->assertStringNotContainsString('1 0 0 rg 0 0 100 50 re f', $output);
    }

    public function testFormInPageXObjectDictionary(): void
    {
        $form = new FormXObject(
            bbox: Rectangle::fromDimensions(100, 50),
            operators: '',
            resources: new ResourceDictionary(),
        );

        $catalog = $this->createMinimalCatalogWithForm($form);
        $serializer = new PdfSerializer(compress: false);
        $output = $serializer->serialize($catalog);

        $this->assertMatchesRegularExpression('/\/XObject <<[^>]*\/X1 \d+ 0 R/', $output);
    }
}
