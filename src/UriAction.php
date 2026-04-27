<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class UriAction implements Action
{
    public function __construct(
        public readonly string $uri,
    ) {}

    public function actionType(): string
    {
        return 'URI';
    }
}
