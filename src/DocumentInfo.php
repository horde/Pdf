<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class DocumentInfo
{
    public function __construct(
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $subject = null,
        public readonly ?string $keywords = null,
        public readonly ?string $creator = null,
        public readonly ?string $creationDate = null,
    ) {}
}
