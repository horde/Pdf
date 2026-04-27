<?php

declare(strict_types=1);

use Horde\Pdf\JpegParser;
use Horde\Pdf\PdfException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JpegParser::class)]
class JpegParserTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = __DIR__ . '/fixtures';
    }

    public function testParseJpegFile(): void
    {
        $path = $this->createTempJpeg(80, 60);
        $image = JpegParser::parseFile($path);

        $this->assertSame(80, $image->width);
        $this->assertSame(60, $image->height);
        $this->assertSame('DeviceRGB', $image->colorSpace->pdfName());
        $this->assertSame(8, $image->bitsPerComponent);
        $this->assertSame('DCTDecode', $image->filter);
        $this->assertNotEmpty($image->data);

        @unlink($path);
    }

    public function testNonExistentFileThrows(): void
    {
        $this->expectException(PdfException::class);
        JpegParser::parseFile('/tmp/nonexistent-' . uniqid() . '.jpg');
    }

    public function testNonJpegFileThrows(): void
    {
        $this->expectException(PdfException::class);
        $pngPath = $this->fixtureDir . '/horde-power1.png';
        if (!is_readable($pngPath)) {
            $this->markTestSkipped('PNG fixture not available');
        }
        JpegParser::parseFile($pngPath);
    }

    private function createTempJpeg(int $w, int $h): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD extension not available');
        }

        $img = imagecreatetruecolor($w, $h);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);

        $path = tempnam(sys_get_temp_dir(), 'horde_pdf_test_') . '.jpg';
        imagejpeg($img, $path, 75);
        imagedestroy($img);

        return $path;
    }
}
