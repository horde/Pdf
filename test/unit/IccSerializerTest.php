<?php

declare(strict_types=1);

namespace Horde\Pdf\Test;

use Horde\Pdf\ContentStream;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\IccBasedColorSpace;
use Horde\Pdf\IccColor;
use Horde\Pdf\IccProfile;
use Horde\Pdf\Page;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\Rectangle;
use Horde\Pdf\ResourceDictionary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfSerializer::class)]
#[CoversClass(IccBasedColorSpace::class)]
#[CoversClass(IccProfile::class)]
final class IccSerializerTest extends TestCase
{
    private function createCatalogWithIcc(IccBasedColorSpace $cs): DocumentCatalog
    {
        $catalog = new DocumentCatalog();
        $resources = new ResourceDictionary();
        $resources->addColorSpace('CS1', $cs);
        $content = new ContentStream('/CS1 cs 0.500 0.300 0.200 sc', $resources);
        $page = new Page(Rectangle::fromDimensions(595.28, 841.89));
        $page->addContentStream($content);
        $catalog->addPage($page);
        return $catalog;
    }

    public function testColorSpaceInResourceDictionary(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('FAKEPROFILE', 3));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: false);
        $pdf = $serializer->serialize($catalog);

        $this->assertStringContainsString('/ColorSpace <<', $pdf);
        $this->assertMatchesRegularExpression('#/CS1 \d+ 0 R#', $pdf);
    }

    public function testIccArrayObjectReferencesStream(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('FAKEPROFILE', 3));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: false);
        $pdf = $serializer->serialize($catalog);

        $this->assertMatchesRegularExpression('#\[/ICCBased \d+ 0 R\]#', $pdf);
    }

    public function testIccStreamHasCorrectAttributes(): void
    {
        $profileData = str_repeat('X', 64);
        $cs = new IccBasedColorSpace(new IccProfile($profileData, 3));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: false);
        $pdf = $serializer->serialize($catalog);

        $this->assertStringContainsString('/N 3', $pdf);
        $this->assertStringContainsString('/Alternate /DeviceRGB', $pdf);
        $this->assertStringContainsString('/Length 64', $pdf);
    }

    public function testIccStreamContainsProfileData(): void
    {
        $profileData = 'TESTPROFILEDATA1234';
        $cs = new IccBasedColorSpace(new IccProfile($profileData, 3));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: false);
        $pdf = $serializer->serialize($catalog);

        $this->assertStringContainsString($profileData, $pdf);
    }

    public function testGrayProfileAlternate(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('GRAYDATA', 1));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: false);
        $pdf = $serializer->serialize($catalog);

        $this->assertStringContainsString('/N 1', $pdf);
        $this->assertStringContainsString('/Alternate /DeviceGray', $pdf);
    }

    public function testCmykProfileAlternate(): void
    {
        $cs = new IccBasedColorSpace(new IccProfile('CMYKDATA', 4));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: false);
        $pdf = $serializer->serialize($catalog);

        $this->assertStringContainsString('/N 4', $pdf);
        $this->assertStringContainsString('/Alternate /DeviceCMYK', $pdf);
    }

    public function testCompressedIccStream(): void
    {
        $profileData = str_repeat('ABCDEFGH', 100);
        $cs = new IccBasedColorSpace(new IccProfile($profileData, 3));
        $catalog = $this->createCatalogWithIcc($cs);

        $serializer = new PdfSerializer(compress: true);
        $pdf = $serializer->serialize($catalog);

        $this->assertStringContainsString('/Filter /FlateDecode', $pdf);
        $this->assertStringNotContainsString($profileData, $pdf);
    }
}
