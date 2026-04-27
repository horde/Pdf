<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class PngParser
{
    private const SIGNATURE = "\x89PNG\r\n\x1A\n";

    public static function parseFile(string $path): ImageXObject
    {
        $f = @fopen($path, 'rb');
        if ($f === false) {
            throw new PdfException(sprintf('Unable to open image file: %s', $path));
        }

        try {
            return self::parse($f, $path);
        } finally {
            fclose($f);
        }
    }

    /**
     * @param resource $f
     */
    private static function parse($f, string $path): ImageXObject
    {
        if (fread($f, 8) !== self::SIGNATURE) {
            throw new PdfException(sprintf('Not a PNG file: %s', $path));
        }

        fread($f, 4);
        if (fread($f, 4) !== 'IHDR') {
            throw new PdfException(sprintf('Incorrect PNG file: %s', $path));
        }

        $width = self::readInt($f);
        $height = self::readInt($f);
        $bpc = ord((string) fread($f, 1));

        if ($bpc > 8) {
            throw new PdfException(sprintf('16-bit depth not supported: %s', $path));
        }

        $ct = ord((string) fread($f, 1));
        $colorSpace = match ($ct) {
            0 => new DeviceGray(),
            2 => new DeviceRgb(),
            3 => new DeviceRgb(),
            default => throw new PdfException(sprintf('Alpha channel not supported: %s', $path)),
        };

        if (ord((string) fread($f, 1)) !== 0) {
            throw new PdfException(sprintf('Unknown compression method: %s', $path));
        }
        if (ord((string) fread($f, 1)) !== 0) {
            throw new PdfException(sprintf('Unknown filter method: %s', $path));
        }
        if (ord((string) fread($f, 1)) !== 0) {
            throw new PdfException(sprintf('Interlacing not supported: %s', $path));
        }

        fread($f, 4);

        $colors = ($ct === 2) ? 3 : 1;
        $decodeParms = '/DecodeParms <</Predictor 15 /Colors ' . $colors
            . ' /BitsPerComponent ' . $bpc
            . ' /Columns ' . $width . '>>';

        $pal = '';
        $trns = [];
        $data = '';

        do {
            $n = self::readInt($f);
            $type = (string) fread($f, 4);

            if ($type === 'PLTE') {
                $pal = (string) fread($f, $n);
                fread($f, 4);
            } elseif ($type === 'tRNS') {
                $t = (string) fread($f, $n);
                $trns = match ($ct) {
                    0 => [ord($t[1])],
                    2 => [ord($t[1]), ord($t[3]), ord($t[5])],
                    default => (($pos = strpos($t, "\0")) !== false) ? [$pos] : [],
                };
                fread($f, 4);
            } elseif ($type === 'IDAT') {
                $data .= (string) fread($f, $n);
                fread($f, 4);
            } elseif ($type === 'IEND') {
                break;
            } else {
                fread($f, $n + 4);
            }
        } while ($n);

        if ($ct === 3 && $pal === '') {
            throw new PdfException(sprintf('Missing palette in: %s', $path));
        }

        return new ImageXObject(
            width: $width,
            height: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bpc,
            filter: 'FlateDecode',
            data: $data,
            decodeParms: $decodeParms,
            palette: ($pal !== '') ? $pal : null,
            transparency: ($trns !== []) ? $trns : null,
        );
    }

    /**
     * @param resource $f
     */
    private static function readInt($f): int
    {
        $i  = ord((string) fread($f, 1)) << 24;
        $i += ord((string) fread($f, 1)) << 16;
        $i += ord((string) fread($f, 1)) << 8;
        $i += ord((string) fread($f, 1));

        return $i;
    }
}
