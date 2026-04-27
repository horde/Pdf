<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class ViewerPreferences
{
    public function __construct(
        public readonly ZoomMode $zoomMode = ZoomMode::DefaultMode,
        public readonly LayoutMode $layoutMode = LayoutMode::DefaultMode,
        public readonly ?int $zoomPercent = null,
    ) {}
}
