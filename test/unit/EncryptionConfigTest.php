<?php

declare(strict_types=1);

use Horde\Pdf\EncryptionAlgorithm;
use Horde\Pdf\EncryptionConfig;
use Horde\Pdf\EncryptionPermissions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptionConfig::class)]
#[CoversClass(EncryptionPermissions::class)]
#[CoversClass(EncryptionAlgorithm::class)]
class EncryptionConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new EncryptionConfig();
        $this->assertSame(EncryptionAlgorithm::AES256, $config->algorithm);
        $this->assertSame('', $config->ownerPassword);
        $this->assertSame('', $config->userPassword);
        $this->assertEmpty($config->permissions);
    }

    public function testPermissionFlagsNoPermissions(): void
    {
        $config = new EncryptionConfig();
        $flags = $config->permissionFlags();

        // Bits 7-8 set (0xC0) and bits 13-32 set (0xFFFFF000)
        $this->assertSame((int) 0xFFFFF0C0, $flags);
        // No permission bits 3-6 and 9-12 set
        $this->assertSame(0, $flags & 0x04); // Print not granted
        $this->assertSame(0, $flags & 0x08); // Modify not granted
    }

    public function testPermissionFlagsWithPrint(): void
    {
        $config = new EncryptionConfig(permissions: [EncryptionPermissions::Print]);
        $flags = $config->permissionFlags();
        $this->assertNotSame(0, $flags & EncryptionPermissions::Print->value);
    }

    public function testPermissionFlagsAllPermissions(): void
    {
        $config = new EncryptionConfig(permissions: [
            EncryptionPermissions::Print,
            EncryptionPermissions::Modify,
            EncryptionPermissions::Copy,
            EncryptionPermissions::Annotate,
            EncryptionPermissions::FillForms,
            EncryptionPermissions::ExtractAccessibility,
            EncryptionPermissions::Assemble,
            EncryptionPermissions::PrintHighQuality,
        ]);
        $flags = $config->permissionFlags();

        $this->assertNotSame(0, $flags & 0x04);
        $this->assertNotSame(0, $flags & 0x08);
        $this->assertNotSame(0, $flags & 0x10);
        $this->assertNotSame(0, $flags & 0x20);
        $this->assertNotSame(0, $flags & 0x100);
        $this->assertNotSame(0, $flags & 0x200);
        $this->assertNotSame(0, $flags & 0x400);
        $this->assertNotSame(0, $flags & 0x800);
    }

    public function testCustomPasswords(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES128,
            ownerPassword: 'owner123',
            userPassword: 'user456',
        );
        $this->assertSame(EncryptionAlgorithm::AES128, $config->algorithm);
        $this->assertSame('owner123', $config->ownerPassword);
        $this->assertSame('user456', $config->userPassword);
    }
}
