<?php

declare(strict_types=1);

use Horde\Pdf\DeviceGray;
use Horde\Pdf\DeviceRgb;
use Horde\Pdf\PdfException;
use Horde\Pdf\PngParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PngParser::class)]
class PngParserTest extends TestCase
{
    public function testParseRgbPng(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $img = imagecreatetruecolor(20, 15);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 0, 0));
        $path = tempnam(sys_get_temp_dir(), 'horde_pdf_png_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        try {
            $xobj = PngParser::parseFile($path);

            $this->assertSame(20, $xobj->width);
            $this->assertSame(15, $xobj->height);
            $this->assertInstanceOf(DeviceRgb::class, $xobj->colorSpace);
            $this->assertSame(8, $xobj->bitsPerComponent);
            $this->assertSame('FlateDecode', $xobj->filter);
            $this->assertNotEmpty($xobj->data);
            $this->assertNotNull($xobj->decodeParms);
            $this->assertStringContainsString('/Predictor 15', $xobj->decodeParms);
            $this->assertStringContainsString('/Colors 3', $xobj->decodeParms);
        } finally {
            @unlink($path);
        }
    }

    public function testParseGrayscalePng(): void
    {
        if (!function_exists('imagecreate')) {
            $this->markTestSkipped('GD extension not available');
        }

        $img = imagecreate(10, 10);
        imagecolorallocate($img, 128, 128, 128);
        $path = tempnam(sys_get_temp_dir(), 'horde_pdf_png_gray_') . '.png';
        imagepng($img, $path);
        imagedestroy($img);

        try {
            $xobj = PngParser::parseFile($path);

            $this->assertSame(10, $xobj->width);
            $this->assertSame(10, $xobj->height);
            $this->assertNotEmpty($xobj->data);
            $this->assertNotNull($xobj->decodeParms);
        } finally {
            @unlink($path);
        }
    }

    public function testRejectsNonPngFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'horde_pdf_notpng_');
        file_put_contents($path, 'not a png file');

        try {
            $this->expectException(PdfException::class);
            $this->expectExceptionMessage('Not a PNG file');
            PngParser::parseFile($path);
        } finally {
            @unlink($path);
        }
    }

    public function testRejectsUnreadableFile(): void
    {
        $this->expectException(PdfException::class);
        $this->expectExceptionMessage('Unable to open');
        PngParser::parseFile('/nonexistent/path.png');
    }
}
