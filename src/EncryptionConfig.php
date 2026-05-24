<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class EncryptionConfig
{
    /**
     * @param array<EncryptionPermissions> $permissions
     */
    public function __construct(
        public readonly EncryptionAlgorithm $algorithm = EncryptionAlgorithm::AES256,
        public readonly string $ownerPassword = '',
        public readonly string $userPassword = '',
        public readonly array $permissions = [],
    ) {}

    public function permissionFlags(): int
    {
        // Bits 1-2 must be zero, bits 7-8 must be 1 (reserved, per spec)
        // Bits 13-32 must be 1 (reserved)
        // Permission bits: 3=print(4), 4=modify(8), 5=copy(16), 6=annotate(32),
        //   9=fillforms(256), 10=extract(512), 11=assemble(1024), 12=printhq(2048)
        $flags = (int) 0xFFFFF0C0;

        foreach ($this->permissions as $perm) {
            $flags |= $perm->value;
        }

        return $flags;
    }
}
