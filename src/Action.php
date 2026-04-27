<?php

declare(strict_types=1);

namespace Horde\Pdf;

interface Action
{
    public function actionType(): string;
}
