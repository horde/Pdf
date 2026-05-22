<?php

declare(strict_types=1);

namespace Horde\Pdf\TrueType;

final class Subsetter
{
    /**
     * @param array<int> $codepoints Unicode codepoints used in the document
     */
    public static function subset(FontData $fontData, array $codepoints): SubsetResult
    {
        $usedGlyphs = [0 => true];
        $cidToOldGid = [0 => 0];

        foreach ($codepoints as $cp) {
            $gid = $fontData->glyphIdForCodepoint($cp);
            if ($gid !== 0) {
                $usedGlyphs[$gid] = true;
                $cidToOldGid[$cp] = $gid;
            }
        }

        self::resolveComposites($fontData, $usedGlyphs);

        ksort($usedGlyphs);
        $oldGids = array_keys($usedGlyphs);

        $oldToNew = [];
        foreach ($oldGids as $newGid => $oldGid) {
            $oldToNew[$oldGid] = $newGid;
        }

        $numGlyphs = count($oldGids);

        $newGlyf = '';
        $newLoca = [];
        foreach ($oldGids as $oldGid) {
            $newLoca[] = strlen($newGlyf);
            $glyphData = self::extractGlyph($fontData, $oldGid);
            if ($glyphData !== '' && self::isComposite($glyphData)) {
                $glyphData = self::remapCompositeGlyph($glyphData, $oldToNew);
            }
            $newGlyf .= $glyphData;
            while (strlen($newGlyf) % 4 !== 0) {
                $newGlyf .= "\x00";
            }
        }
        $newLoca[] = strlen($newGlyf);

        $widths = [];
        foreach ($oldGids as $newGid => $oldGid) {
            $widths[$newGid] = $fontData->advanceWidth($oldGid);
        }

        $cidToNewGid = [];
        foreach ($cidToOldGid as $cid => $oldGid) {
            $cidToNewGid[$cid] = $oldToNew[$oldGid] ?? 0;
        }

        $ttfBinary = self::buildTtf($fontData, $newGlyf, $newLoca, $widths, $numGlyphs, $cidToNewGid);

        return new SubsetResult(
            data: $ttfBinary,
            glyphMap: $oldToNew,
            widths: $widths,
            cidToGid: $cidToNewGid,
        );
    }

    /**
     * @param array<int, bool> $usedGlyphs
     */
    private static function resolveComposites(FontData $fontData, array &$usedGlyphs): void
    {
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach (array_keys($usedGlyphs) as $gid) {
                $glyphData = self::extractGlyph($fontData, $gid);
                if ($glyphData === '' || !self::isComposite($glyphData)) {
                    continue;
                }
                $components = self::getCompositeComponents($glyphData);
                foreach ($components as $componentGid) {
                    if (!isset($usedGlyphs[$componentGid])) {
                        $usedGlyphs[$componentGid] = true;
                        $changed = true;
                    }
                }
            }
        }
    }

    private static function extractGlyph(FontData $fontData, int $glyphId): string
    {
        if (!isset($fontData->loca[$glyphId]) || !isset($fontData->loca[$glyphId + 1])) {
            return '';
        }
        $start = $fontData->loca[$glyphId];
        $end = $fontData->loca[$glyphId + 1];
        if ($end <= $start) {
            return '';
        }
        return substr($fontData->glyfRaw, $start, $end - $start);
    }

    private static function isComposite(string $glyphData): bool
    {
        if (strlen($glyphData) < 2) {
            return false;
        }
        $numContours = (ord($glyphData[0]) << 8) | ord($glyphData[1]);
        return $numContours >= 0x8000;
    }

    /**
     * @return array<int>
     */
    private static function getCompositeComponents(string $glyphData): array
    {
        $components = [];
        $offset = 10;
        $moreComponents = true;

        while ($moreComponents && $offset + 4 <= strlen($glyphData)) {
            $flags = (ord($glyphData[$offset]) << 8) | ord($glyphData[$offset + 1]);
            $glyphIndex = (ord($glyphData[$offset + 2]) << 8) | ord($glyphData[$offset + 3]);
            $components[] = $glyphIndex;
            $offset += 4;

            if ($flags & 0x0001) {
                $offset += 4;
            } else {
                $offset += 2;
            }

            if ($flags & 0x0008) {
                $offset += 2;
            } elseif ($flags & 0x0040) {
                $offset += 4;
            } elseif ($flags & 0x0080) {
                $offset += 8;
            }

            $moreComponents = ($flags & 0x0020) !== 0;
        }

        return $components;
    }

    /**
     * @param array<int, int> $oldToNew
     */
    private static function remapCompositeGlyph(string $glyphData, array $oldToNew): string
    {
        $result = $glyphData;
        $offset = 10;
        $moreComponents = true;

        while ($moreComponents && $offset + 4 <= strlen($result)) {
            $flags = (ord($result[$offset]) << 8) | ord($result[$offset + 1]);
            $oldGid = (ord($result[$offset + 2]) << 8) | ord($result[$offset + 3]);
            $newGid = $oldToNew[$oldGid] ?? 0;
            $result[$offset + 2] = chr(($newGid >> 8) & 0xFF);
            $result[$offset + 3] = chr($newGid & 0xFF);
            $offset += 4;

            if ($flags & 0x0001) {
                $offset += 4;
            } else {
                $offset += 2;
            }

            if ($flags & 0x0008) {
                $offset += 2;
            } elseif ($flags & 0x0040) {
                $offset += 4;
            } elseif ($flags & 0x0080) {
                $offset += 8;
            }

            $moreComponents = ($flags & 0x0020) !== 0;
        }

        return $result;
    }

    /**
     * @param array<int> $newLoca
     * @param array<int, int> $widths
     * @param array<int, int> $cidToNewGid
     */
    private static function buildTtf(
        FontData $fontData,
        string $newGlyf,
        array $newLoca,
        array $widths,
        int $numGlyphs,
        array $cidToNewGid,
    ): string {
        $tables = [];

        $tables['head'] = self::buildHead($fontData);
        $tables['hhea'] = self::buildHhea($fontData, $numGlyphs);
        $tables['maxp'] = self::buildMaxp($numGlyphs);
        $tables['hmtx'] = self::buildHmtx($widths, $numGlyphs);
        $tables['loca'] = self::buildLoca($newLoca);
        $tables['glyf'] = $newGlyf;
        $tables['cmap'] = self::buildCmap($cidToNewGid);
        $tables['post'] = self::buildPost();
        $tables['name'] = self::buildName($fontData->postScriptName);

        $numTablesCount = count($tables);
        $searchRange = 1;
        $entrySelector = 0;
        while ($searchRange * 2 <= $numTablesCount) {
            $searchRange *= 2;
            $entrySelector++;
        }
        $searchRange *= 16;
        $rangeShift = ($numTablesCount * 16) - $searchRange;

        $headerSize = 12 + ($numTablesCount * 16);

        $offset = $headerSize;
        $tableOffsets = [];
        foreach ($tables as $tag => $data) {
            $tableOffsets[$tag] = $offset;
            $padded = strlen($data);
            while ($padded % 4 !== 0) {
                $padded++;
            }
            $offset += $padded;
        }

        $header = pack('Nnnnn', 0x00010000, $numTablesCount, $searchRange, $entrySelector, $rangeShift);

        $directory = '';
        foreach ($tables as $tag => $data) {
            $directory .= $tag;
            $directory .= pack('N', self::checksum($data));
            $directory .= pack('N', $tableOffsets[$tag]);
            $directory .= pack('N', strlen($data));
        }

        $body = '';
        foreach ($tables as $data) {
            $body .= $data;
            while (strlen($body) % 4 !== 0) {
                $body .= "\x00";
            }
        }

        return $header . $directory . $body;
    }

    private static function buildHead(FontData $fontData): string
    {
        $orig = substr($fontData->rawData, $fontData->tableDirectory['head']['offset'], $fontData->tableDirectory['head']['length']);
        $head = $orig;
        $head[8] = "\x00";
        $head[9] = "\x00";
        $head[10] = "\x00";
        $head[11] = "\x00";
        $head[50] = "\x00";
        $head[51] = "\x01";
        return $head;
    }

    private static function buildHhea(FontData $fontData, int $numGlyphs): string
    {
        $orig = substr($fontData->rawData, $fontData->tableDirectory['hhea']['offset'], $fontData->tableDirectory['hhea']['length']);
        $hhea = $orig;
        $hhea[34] = chr(($numGlyphs >> 8) & 0xFF);
        $hhea[35] = chr($numGlyphs & 0xFF);
        return $hhea;
    }

    private static function buildMaxp(int $numGlyphs): string
    {
        $data = pack('N', 0x00010000);
        $data .= pack('n', $numGlyphs);
        $data .= str_repeat("\x00", 26);
        return $data;
    }

    /**
     * @param array<int, int> $widths
     */
    private static function buildHmtx(array $widths, int $numGlyphs): string
    {
        $data = '';
        for ($i = 0; $i < $numGlyphs; $i++) {
            $data .= pack('n', $widths[$i] ?? 0);
            $data .= pack('n', 0);
        }
        return $data;
    }

    /**
     * @param array<int> $loca
     */
    private static function buildLoca(array $loca): string
    {
        $data = '';
        foreach ($loca as $offset) {
            $data .= pack('N', $offset);
        }
        return $data;
    }

    /**
     * @param array<int, int> $cidToNewGid
     */
    private static function buildCmap(array $cidToNewGid): string
    {
        $header = pack('nn', 0, 1);
        $header .= pack('nnN', 3, 10, 12);

        $groups = [];
        $sorted = $cidToNewGid;
        ksort($sorted);
        unset($sorted[0]);

        $prevCp = -2;
        $prevGid = -2;
        $startCp = 0;
        $startGid = 0;

        foreach ($sorted as $cp => $gid) {
            if ($cp === $prevCp + 1 && $gid === $prevGid + 1) {
                $prevCp = $cp;
                $prevGid = $gid;
                continue;
            }
            if ($prevCp >= 0) {
                $groups[] = [$startCp, $prevCp, $startGid];
            }
            $startCp = $cp;
            $startGid = $gid;
            $prevCp = $cp;
            $prevGid = $gid;
        }
        if ($prevCp >= 0) {
            $groups[] = [$startCp, $prevCp, $startGid];
        }

        $numGroups = count($groups);
        $subtableLength = 16 + ($numGroups * 12);
        $subtable = pack('nna*', 12, 0, pack('NNN', $subtableLength, 0, $numGroups));
        foreach ($groups as [$start, $end, $gid]) {
            $subtable .= pack('NNN', $start, $end, $gid);
        }

        return $header . $subtable;
    }

    private static function buildPost(): string
    {
        $data = pack('N', 0x00030000);
        $data .= pack('N', 0);
        $data .= pack('nn', 0, 0);
        $data .= pack('N', 0);
        $data .= str_repeat("\x00", 16);
        return $data;
    }

    private static function buildName(string $postScriptName): string
    {
        $nameBytes = '';
        $len = strlen($postScriptName);
        for ($i = 0; $i < $len; $i++) {
            $nameBytes .= "\x00" . $postScriptName[$i];
        }

        $storageOffset = 6 + (3 * 12);
        $record = function (int $nameId, int $offset, int $length): string {
            return pack('nnnnn', 3, 1, 0x0409, $nameId, $length) . pack('n', $offset);
        };

        $header = pack('nn', 0, 3);
        $header .= pack('n', $storageOffset);
        $header .= $record(1, 0, strlen($nameBytes));
        $header .= $record(2, 0, strlen($nameBytes));
        $header .= $record(6, 0, strlen($nameBytes));

        return $header . $nameBytes;
    }

    private static function checksum(string $data): int
    {
        while (strlen($data) % 4 !== 0) {
            $data .= "\x00";
        }
        $sum = 0;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i += 4) {
            $sum += (ord($data[$i]) << 24) | (ord($data[$i + 1]) << 16)
                | (ord($data[$i + 2]) << 8) | ord($data[$i + 3]);
            $sum &= 0xFFFFFFFF;
        }
        return $sum;
    }
}
