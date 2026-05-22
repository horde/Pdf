<?php

declare(strict_types=1);

namespace Horde\Pdf\TrueType;

use Horde\Pdf\PdfException;

final class FontParser
{
    public static function parseFile(string $path): FontData
    {
        $data = file_get_contents($path);
        if ($data === false) {
            throw new PdfException(sprintf('Cannot read font file: %s', $path));
        }

        return self::parse($data);
    }

    public static function parse(string $data): FontData
    {
        $pos = 0;

        $sfVersion = self::readUint32($data, $pos);
        if ($sfVersion !== 0x00010000 && $sfVersion !== 0x74727565) {
            throw new PdfException('Not a valid TrueType font');
        }

        $numTables = self::readUint16($data, $pos + 4);
        $pos = 12;

        $tableDirectory = [];
        for ($i = 0; $i < $numTables; $i++) {
            $tag = substr($data, $pos, 4);
            $offset = self::readUint32($data, $pos + 8);
            $length = self::readUint32($data, $pos + 12);
            $tableDirectory[$tag] = ['offset' => $offset, 'length' => $length];
            $pos += 16;
        }

        $head = self::parseHead($data, $tableDirectory);
        $hhea = self::parseHhea($data, $tableDirectory);
        $maxp = self::parseMaxp($data, $tableDirectory);
        $os2 = self::parseOs2($data, $tableDirectory);
        $post = self::parsePost($data, $tableDirectory);
        $name = self::parseName($data, $tableDirectory);
        $cmap = self::parseCmap($data, $tableDirectory);
        $hmtx = self::parseHmtx($data, $tableDirectory, $hhea['numberOfHMetrics'], $maxp['numGlyphs']);
        $loca = self::parseLoca($data, $tableDirectory, $head['indexToLocFormat'], $maxp['numGlyphs']);

        $glyfRaw = '';
        if (isset($tableDirectory['glyf'])) {
            $glyfRaw = substr($data, $tableDirectory['glyf']['offset'], $tableDirectory['glyf']['length']);
        }

        return new FontData(
            unitsPerEm: $head['unitsPerEm'],
            indexToLocFormat: $head['indexToLocFormat'],
            numGlyphs: $maxp['numGlyphs'],
            numberOfHMetrics: $hhea['numberOfHMetrics'],
            ascender: $hhea['ascender'],
            descender: $hhea['descender'],
            capHeight: $os2['capHeight'],
            italicAngle: $post['italicAngle'],
            usWeightClass: $os2['usWeightClass'],
            fsSelection: $os2['fsSelection'],
            underlinePosition: $post['underlinePosition'],
            underlineThickness: $post['underlineThickness'],
            isFixedPitch: $post['isFixedPitch'],
            postScriptName: $name['postScriptName'],
            fontFamily: $name['fontFamily'],
            fontSubfamily: $name['fontSubfamily'],
            xMin: $head['xMin'],
            yMin: $head['yMin'],
            xMax: $head['xMax'],
            yMax: $head['yMax'],
            sTypoAscender: $os2['sTypoAscender'],
            sTypoDescender: $os2['sTypoDescender'],
            cmap: $cmap,
            hmtx: $hmtx,
            loca: $loca,
            glyfRaw: $glyfRaw,
            rawData: $data,
            tableDirectory: $tableDirectory,
        );
    }

    /**
     * @return array{unitsPerEm: int, indexToLocFormat: int, xMin: int, yMin: int, xMax: int, yMax: int}
     */
    private static function parseHead(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['head'] ?? throw new PdfException('Missing head table');
        $o = $t['offset'];

        return [
            'unitsPerEm' => self::readUint16($data, $o + 18),
            'xMin' => self::readInt16($data, $o + 36),
            'yMin' => self::readInt16($data, $o + 38),
            'xMax' => self::readInt16($data, $o + 40),
            'yMax' => self::readInt16($data, $o + 42),
            'indexToLocFormat' => self::readInt16($data, $o + 50),
        ];
    }

    /**
     * @return array{ascender: int, descender: int, numberOfHMetrics: int}
     */
    private static function parseHhea(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['hhea'] ?? throw new PdfException('Missing hhea table');
        $o = $t['offset'];

        return [
            'ascender' => self::readInt16($data, $o + 4),
            'descender' => self::readInt16($data, $o + 6),
            'numberOfHMetrics' => self::readUint16($data, $o + 34),
        ];
    }

    /**
     * @return array{numGlyphs: int}
     */
    private static function parseMaxp(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['maxp'] ?? throw new PdfException('Missing maxp table');
        $o = $t['offset'];

        return [
            'numGlyphs' => self::readUint16($data, $o + 4),
        ];
    }

    /**
     * @return array{usWeightClass: int, fsSelection: int, sTypoAscender: int, sTypoDescender: int, capHeight: int}
     */
    private static function parseOs2(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['OS/2'] ?? null;
        if ($t === null) {
            return [
                'usWeightClass' => 400,
                'fsSelection' => 0,
                'sTypoAscender' => 0,
                'sTypoDescender' => 0,
                'capHeight' => 0,
            ];
        }
        $o = $t['offset'];
        $version = self::readUint16($data, $o);

        return [
            'usWeightClass' => self::readUint16($data, $o + 4),
            'fsSelection' => self::readUint16($data, $o + 62),
            'sTypoAscender' => self::readInt16($data, $o + 68),
            'sTypoDescender' => self::readInt16($data, $o + 70),
            'capHeight' => $version >= 2 ? self::readInt16($data, $o + 88) : 0,
        ];
    }

    /**
     * @return array{italicAngle: int, isFixedPitch: bool, underlinePosition: int, underlineThickness: int}
     */
    private static function parsePost(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['post'] ?? throw new PdfException('Missing post table');
        $o = $t['offset'];

        $italicAngleFixed = self::readInt32($data, $o + 4);
        $italicAngle = $italicAngleFixed >> 16;

        return [
            'italicAngle' => $italicAngle,
            'underlinePosition' => self::readInt16($data, $o + 8),
            'underlineThickness' => self::readInt16($data, $o + 10),
            'isFixedPitch' => self::readUint32($data, $o + 12) !== 0,
        ];
    }

    /**
     * @return array{postScriptName: string, fontFamily: string, fontSubfamily: string}
     */
    private static function parseName(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['name'] ?? throw new PdfException('Missing name table');
        $o = $t['offset'];

        $count = self::readUint16($data, $o + 2);
        $storageOffset = $o + self::readUint16($data, $o + 4);

        $names = ['postScriptName' => '', 'fontFamily' => '', 'fontSubfamily' => ''];

        for ($i = 0; $i < $count; $i++) {
            $recOffset = $o + 6 + ($i * 12);
            $platformId = self::readUint16($data, $recOffset);
            $encodingId = self::readUint16($data, $recOffset + 2);
            $nameId = self::readUint16($data, $recOffset + 6);
            $length = self::readUint16($data, $recOffset + 8);
            $strOffset = self::readUint16($data, $recOffset + 10);

            $str = substr($data, $storageOffset + $strOffset, $length);

            if ($platformId === 3 && $encodingId === 1) {
                $str = self::decodeUtf16Be($str);
            } elseif ($platformId === 1 && $encodingId === 0) {
                // MacRoman, already ASCII-compatible
            } else {
                continue;
            }

            match ($nameId) {
                1 => $names['fontFamily'] = $names['fontFamily'] ?: $str,
                2 => $names['fontSubfamily'] = $names['fontSubfamily'] ?: $str,
                6 => $names['postScriptName'] = $names['postScriptName'] ?: $str,
                default => null,
            };
        }

        if ($names['postScriptName'] === '') {
            $names['postScriptName'] = str_replace(' ', '-', $names['fontFamily']);
        }

        return $names;
    }

    /**
     * @return array<int, int> codepoint → glyph ID
     */
    private static function parseCmap(string $data, array $tableDirectory): array
    {
        $t = $tableDirectory['cmap'] ?? throw new PdfException('Missing cmap table');
        $o = $t['offset'];

        $numSubtables = self::readUint16($data, $o + 2);
        $format12Offset = null;
        $format4Offset = null;

        for ($i = 0; $i < $numSubtables; $i++) {
            $subtableOffset = $o + 4 + ($i * 8);
            $platformId = self::readUint16($data, $subtableOffset);
            $encodingId = self::readUint16($data, $subtableOffset + 2);
            $offset = self::readUint32($data, $subtableOffset + 4);
            $absOffset = $o + $offset;

            $format = self::readUint16($data, $absOffset);

            if ($format === 12 && ($platformId === 3 && $encodingId === 10)) {
                $format12Offset = $absOffset;
            } elseif ($format === 4 && $platformId === 3 && $encodingId === 1) {
                $format4Offset = $absOffset;
            }
        }

        if ($format12Offset !== null) {
            return self::parseCmapFormat12($data, $format12Offset);
        }

        if ($format4Offset !== null) {
            return self::parseCmapFormat4($data, $format4Offset);
        }

        throw new PdfException('No supported cmap subtable found (need format 4 or 12)');
    }

    /**
     * @return array<int, int>
     */
    private static function parseCmapFormat4(string $data, int $offset): array
    {
        $segCount = self::readUint16($data, $offset + 6) / 2;
        $endCodesOffset = $offset + 14;
        $startCodesOffset = $endCodesOffset + ($segCount * 2) + 2;
        $idDeltaOffset = $startCodesOffset + ($segCount * 2);
        $idRangeOffset = $idDeltaOffset + ($segCount * 2);

        $map = [];

        for ($i = 0; $i < $segCount; $i++) {
            $endCode = self::readUint16($data, $endCodesOffset + $i * 2);
            $startCode = self::readUint16($data, $startCodesOffset + $i * 2);
            $idDelta = self::readInt16($data, $idDeltaOffset + $i * 2);
            $idRangeOffsetValue = self::readUint16($data, $idRangeOffset + $i * 2);

            if ($startCode === 0xFFFF) {
                break;
            }

            for ($c = $startCode; $c <= $endCode; $c++) {
                if ($idRangeOffsetValue === 0) {
                    $glyphId = ($c + $idDelta) & 0xFFFF;
                } else {
                    $glyphIndexAddress = $idRangeOffset + $i * 2 + $idRangeOffsetValue + ($c - $startCode) * 2;
                    $glyphId = self::readUint16($data, $glyphIndexAddress);
                    if ($glyphId !== 0) {
                        $glyphId = ($glyphId + $idDelta) & 0xFFFF;
                    }
                }
                if ($glyphId !== 0) {
                    $map[$c] = $glyphId;
                }
            }
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private static function parseCmapFormat12(string $data, int $offset): array
    {
        $numGroups = self::readUint32($data, $offset + 12);
        $groupOffset = $offset + 16;
        $map = [];

        for ($i = 0; $i < $numGroups; $i++) {
            $startCode = self::readUint32($data, $groupOffset);
            $endCode = self::readUint32($data, $groupOffset + 4);
            $startGlyphId = self::readUint32($data, $groupOffset + 8);

            for ($c = $startCode; $c <= $endCode; $c++) {
                $map[$c] = $startGlyphId + ($c - $startCode);
            }

            $groupOffset += 12;
        }

        return $map;
    }

    /**
     * @return array<int, int> glyph ID → advance width
     */
    private static function parseHmtx(string $data, array $tableDirectory, int $numberOfHMetrics, int $numGlyphs): array
    {
        $t = $tableDirectory['hmtx'] ?? throw new PdfException('Missing hmtx table');
        $o = $t['offset'];

        $widths = [];
        $lastWidth = 0;

        for ($i = 0; $i < $numberOfHMetrics; $i++) {
            $lastWidth = self::readUint16($data, $o + $i * 4);
            $widths[$i] = $lastWidth;
        }

        for ($i = $numberOfHMetrics; $i < $numGlyphs; $i++) {
            $widths[$i] = $lastWidth;
        }

        return $widths;
    }

    /**
     * @return array<int, int> glyph offsets
     */
    private static function parseLoca(string $data, array $tableDirectory, int $indexToLocFormat, int $numGlyphs): array
    {
        $t = $tableDirectory['loca'] ?? throw new PdfException('Missing loca table');
        $o = $t['offset'];

        $offsets = [];
        $count = $numGlyphs + 1;

        if ($indexToLocFormat === 0) {
            for ($i = 0; $i < $count; $i++) {
                $offsets[$i] = self::readUint16($data, $o + $i * 2) * 2;
            }
        } else {
            for ($i = 0; $i < $count; $i++) {
                $offsets[$i] = self::readUint32($data, $o + $i * 4);
            }
        }

        return $offsets;
    }

    private static function decodeUtf16Be(string $str): string
    {
        $result = '';
        $len = strlen($str);
        for ($i = 0; $i + 1 < $len; $i += 2) {
            $code = (ord($str[$i]) << 8) | ord($str[$i + 1]);
            if ($code < 128) {
                $result .= chr($code);
            } else {
                $result .= mb_chr($code, 'UTF-8');
            }
        }
        return $result;
    }

    private static function readUint16(string $data, int $offset): int
    {
        return (ord($data[$offset]) << 8) | ord($data[$offset + 1]);
    }

    private static function readInt16(string $data, int $offset): int
    {
        $v = self::readUint16($data, $offset);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private static function readUint32(string $data, int $offset): int
    {
        return (ord($data[$offset]) << 24) | (ord($data[$offset + 1]) << 16)
            | (ord($data[$offset + 2]) << 8) | ord($data[$offset + 3]);
    }

    private static function readInt32(string $data, int $offset): int
    {
        $v = self::readUint32($data, $offset);
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }
}
