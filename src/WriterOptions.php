<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class WriterOptions
{
    public function __construct(
        public readonly Orientation $orientation = Orientation::Portrait,
        public readonly Unit $unit = Unit::Millimeter,
        public readonly PageFormat|CustomPageFormat $format = PageFormat::A4,
    ) {}

    /**
     * Create from a legacy parameter array as accepted by Horde_Pdf_Writer.
     *
     * @param array{orientation?: string, unit?: string, format?: string|array{float, float}} $params
     */
    public static function fromLegacy(array $params = []): self
    {
        $orientation = Orientation::Portrait;
        if (isset($params['orientation'])) {
            $o = strtolower($params['orientation']);
            $orientation = match (true) {
                $o === 'l', $o === 'landscape' => Orientation::Landscape,
                default => Orientation::Portrait,
            };
        }

        $unit = Unit::Millimeter;
        if (isset($params['unit'])) {
            $unit = Unit::from($params['unit']);
        }

        $format = PageFormat::A4;
        if (isset($params['format'])) {
            if (is_array($params['format'])) {
                $format = new CustomPageFormat($params['format'][0], $params['format'][1]);
            } else {
                $format = PageFormat::from(strtolower($params['format']));
            }
        }

        return new self($orientation, $unit, $format);
    }

    /**
     * Page format dimensions in points.
     *
     * @return array{float, float} Width and height in points.
     */
    public function formatDimensionsInPoints(): array
    {
        if ($this->format instanceof PageFormat) {
            return $this->format->dimensions();
        }

        $scale = $this->unit->scaleFactor();
        return [
            $this->format->width * $scale,
            $this->format->height * $scale,
        ];
    }
}
