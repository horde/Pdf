<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum EncryptionAlgorithm
{
    case AES128;
    case AES256;
}
