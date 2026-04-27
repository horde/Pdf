<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum CoreFont: string
{
    case Courier = 'courier';
    case CourierBold = 'courierB';
    case CourierItalic = 'courierI';
    case CourierBoldItalic = 'courierBI';
    case Helvetica = 'helvetica';
    case HelveticaBold = 'helveticaB';
    case HelveticaItalic = 'helveticaI';
    case HelveticaBoldItalic = 'helveticaBI';
    case Times = 'times';
    case TimesBold = 'timesB';
    case TimesItalic = 'timesI';
    case TimesBoldItalic = 'timesBI';
    case Symbol = 'symbol';
    case ZapfDingbats = 'zapfdingbats';

    public function toFont(): Type1Font
    {
        return new Type1Font($this);
    }

    /**
     * Resolve a legacy family + style string pair to a CoreFont case.
     *
     * @return array{self, bool} The resolved CoreFont and whether underline was requested.
     */
    public static function fromFamilyStyle(string $family, string $style = ''): array
    {
        $family = strtolower($family);
        if ($family === 'arial') {
            $family = 'helvetica';
        }

        $style = strtoupper($style);
        $underline = str_contains($style, 'U');
        $style = str_replace('U', '', $style);

        if ($family === 'symbol' || $family === 'zapfdingbats') {
            $style = '';
        }

        if ($style === 'IB') {
            $style = 'BI';
        }

        $key = $family . $style;

        return [self::from($key), $underline];
    }

    public function pdfName(): string
    {
        return match ($this) {
            self::Courier => 'Courier',
            self::CourierBold => 'Courier-Bold',
            self::CourierItalic => 'Courier-Oblique',
            self::CourierBoldItalic => 'Courier-BoldOblique',
            self::Helvetica => 'Helvetica',
            self::HelveticaBold => 'Helvetica-Bold',
            self::HelveticaItalic => 'Helvetica-Oblique',
            self::HelveticaBoldItalic => 'Helvetica-BoldOblique',
            self::Times => 'Times-Roman',
            self::TimesBold => 'Times-Bold',
            self::TimesItalic => 'Times-Italic',
            self::TimesBoldItalic => 'Times-BoldItalic',
            self::Symbol => 'Symbol',
            self::ZapfDingbats => 'ZapfDingbats',
        };
    }

    public function family(): string
    {
        return match ($this) {
            self::Courier, self::CourierBold, self::CourierItalic, self::CourierBoldItalic => 'courier',
            self::Helvetica, self::HelveticaBold, self::HelveticaItalic, self::HelveticaBoldItalic => 'helvetica',
            self::Times, self::TimesBold, self::TimesItalic, self::TimesBoldItalic => 'times',
            self::Symbol => 'symbol',
            self::ZapfDingbats => 'zapfdingbats',
        };
    }

    /**
     * @return array<string, int> Character widths keyed by character.
     */
    public function widths(): array
    {
        $className = $this->legacyClassName();
        $instance = new $className();
        $all = $instance->getWidths();

        return $all[$this->value];
    }

    private function legacyClassName(): string
    {
        return match ($this) {
            self::Courier => 'Horde_Pdf_Font_Courier',
            self::CourierBold => 'Horde_Pdf_Font_Courierb',
            self::CourierItalic => 'Horde_Pdf_Font_Courieri',
            self::CourierBoldItalic => 'Horde_Pdf_Font_Courierbi',
            self::Helvetica => 'Horde_Pdf_Font_Helvetica',
            self::HelveticaBold => 'Horde_Pdf_Font_Helveticab',
            self::HelveticaItalic => 'Horde_Pdf_Font_Helveticai',
            self::HelveticaBoldItalic => 'Horde_Pdf_Font_Helveticabi',
            self::Times => 'Horde_Pdf_Font_Times',
            self::TimesBold => 'Horde_Pdf_Font_Timesb',
            self::TimesItalic => 'Horde_Pdf_Font_Timesi',
            self::TimesBoldItalic => 'Horde_Pdf_Font_Timesbi',
            self::Symbol => 'Horde_Pdf_Font_Symbol',
            self::ZapfDingbats => 'Horde_Pdf_Font_Zapfdingbats',
        };
    }
}
