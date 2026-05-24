<?php

declare(strict_types=1);

use Horde\Pdf\EncryptionAlgorithm;
use Horde\Pdf\EncryptionConfig;
use Horde\Pdf\EncryptionHandler;
use Horde\Pdf\EncryptionPermissions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EncryptionHandler::class)]
#[CoversClass(EncryptionConfig::class)]
class EncryptionHandlerTest extends TestCase
{
    public function testCreateAes256(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            ownerPassword: 'owner',
            userPassword: 'user',
        );
        $handler = EncryptionHandler::create($config, random_bytes(16));

        $this->assertSame(48, strlen($handler->userHash()));
        $this->assertSame(48, strlen($handler->ownerHash()));
        $this->assertSame(32, strlen($handler->userEncKey()));
        $this->assertSame(32, strlen($handler->ownerEncKey()));
        $this->assertSame(16, strlen($handler->permsEncrypted()));
    }

    public function testCreateAes128(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES128,
            ownerPassword: 'owner',
            userPassword: 'user',
        );
        $fileId = random_bytes(16);
        $handler = EncryptionHandler::create($config, $fileId);

        $this->assertSame(32, strlen($handler->ownerHash()));
        $this->assertSame(32, strlen($handler->userHash()));
        $this->assertSame('', $handler->ownerEncKey());
        $this->assertSame('', $handler->userEncKey());
        $this->assertSame('', $handler->permsEncrypted());
    }

    public function testEncryptStreamProducesOutput(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            userPassword: 'test',
        );
        $handler = EncryptionHandler::create($config, random_bytes(16));

        $data = 'Hello, World!';
        $encrypted = $handler->encryptStream($data, 1, 0);

        $this->assertNotSame($data, $encrypted);
        $this->assertGreaterThan(strlen($data), strlen($encrypted));
        // Encrypted data starts with 16-byte IV
        $this->assertGreaterThanOrEqual(16, strlen($encrypted));
    }

    public function testEncryptStringProducesOutput(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES128,
            userPassword: 'test',
        );
        $handler = EncryptionHandler::create($config, random_bytes(16));

        $data = 'Test String';
        $encrypted = $handler->encryptString($data, 5, 0);

        $this->assertNotSame($data, $encrypted);
        $this->assertGreaterThanOrEqual(16, strlen($encrypted));
    }

    public function testDifferentObjectsProduceDifferentCiphertext(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES128,
            userPassword: 'test',
        );
        $handler = EncryptionHandler::create($config, random_bytes(16));

        $data = 'Same input';
        $enc1 = $handler->encryptString($data, 1, 0);
        $enc2 = $handler->encryptString($data, 2, 0);

        // Different due to different per-object keys (AES-128) + random IV
        $this->assertNotSame($enc1, $enc2);
    }

    public function testAes256SameKeyAllObjects(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            userPassword: 'test',
        );
        $handler = EncryptionHandler::create($config, random_bytes(16));

        $data = 'Same input';
        $enc1 = $handler->encryptStream($data, 1, 0);
        $enc2 = $handler->encryptStream($data, 2, 0);

        // Different due to random IV even though same key
        $this->assertNotSame($enc1, $enc2);
        // But both should be same length (same plaintext, same key length)
        $this->assertSame(strlen($enc1), strlen($enc2));
    }
}
