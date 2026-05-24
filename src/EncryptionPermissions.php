<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum EncryptionPermissions: int
{
    case Print = 4;
    case Modify = 8;
    case Copy = 16;
    case Annotate = 32;
    case FillForms = 256;
    case ExtractAccessibility = 512;
    case Assemble = 1024;
    case PrintHighQuality = 2048;
}
