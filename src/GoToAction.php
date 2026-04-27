<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class GoToAction implements Action
{
    public function __construct(
        public readonly Destination $destination,
    ) {}

    public function actionType(): string
    {
        return 'GoTo';
    }
}
