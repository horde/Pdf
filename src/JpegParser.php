<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class JpegParser
{
    public static function parseFile(string $path): ImageXObject
    {
        if (!is_readable($path)) {
            throw new PdfException(sprintf('JPEG file not readable: %s', $path));
        }

        $info = @getimagesize($path);

        if ($info === false) {
            throw new PdfException(sprintf('Not a valid JPEG file: %s', $path));
        }

        if ($info[2] !== IMAGETYPE_JPEG) {
            throw new PdfException(sprintf('Not a JPEG file (type %d): %s', $info[2], $path));
        }

        $data = file_get_contents($path);

        if ($data === false) {
            throw new PdfException(sprintf('Could not read JPEG file: %s', $path));
        }

        $colorSpace = self::colorSpaceFromChannels($info['channels'] ?? 3);

        return new ImageXObject(
            width: $info[0],
            height: $info[1],
            colorSpace: $colorSpace,
            bitsPerComponent: $info['bits'] ?? 8,
            filter: 'DCTDecode',
            data: $data,
        );
    }

    private static function colorSpaceFromChannels(int $channels): ColorSpace
    {
        return match ($channels) {
            1 => new DeviceGray(),
            4 => new DeviceCmyk(),
            default => new DeviceRgb(),
        };
    }
}
