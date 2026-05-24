<?php

declare(strict_types=1);

namespace Horde\Pdf;

final class EncryptionHandler
{
    private const PADDING = "\x28\xBF\x4E\x5E\x4D\x75\x8A\x41\x64\x00\x4B\x49\x43\x72\x00\x23"
        . "\x44\x30\x04\xE5\x60\xD6\x11\x10\x40\x68\x01\x99\x37\xD0\x76\xA8"
        . "\xD2\x63\x69\xD1\x26\xD7\x14\xB2\xF2\xBF\xA0\xB2\x44\x93\x02\x06";

    private function __construct(
        private readonly EncryptionAlgorithm $algorithm,
        private readonly string $fileEncryptionKey,
        private readonly string $ownerHashValue,
        private readonly string $userHashValue,
        private readonly string $ownerEncKeyValue,
        private readonly string $userEncKeyValue,
        private readonly string $permsValue,
        private readonly int $permissionFlags,
    ) {}

    public static function create(EncryptionConfig $config, string $fileId): self
    {
        $permissions = $config->permissionFlags();

        if ($config->algorithm === EncryptionAlgorithm::AES128) {
            return self::createAes128($config, $fileId, $permissions);
        }

        return self::createAes256($config, $permissions);
    }

    public function encryptString(string $data, int $objNum, int $genNum): string
    {
        $key = $this->objectKey($objNum, $genNum);
        return $this->aesEncrypt($data, $key);
    }

    public function encryptStream(string $data, int $objNum, int $genNum): string
    {
        $key = $this->objectKey($objNum, $genNum);
        return $this->aesEncrypt($data, $key);
    }

    public function ownerHash(): string
    {
        return $this->ownerHashValue;
    }

    public function userHash(): string
    {
        return $this->userHashValue;
    }

    public function ownerEncKey(): string
    {
        return $this->ownerEncKeyValue;
    }

    public function userEncKey(): string
    {
        return $this->userEncKeyValue;
    }

    public function permsEncrypted(): string
    {
        return $this->permsValue;
    }

    public function algorithm(): EncryptionAlgorithm
    {
        return $this->algorithm;
    }

    private function objectKey(int $objNum, int $genNum): string
    {
        if ($this->algorithm === EncryptionAlgorithm::AES256) {
            return $this->fileEncryptionKey;
        }

        // AES-128: per-object key derivation
        $data = $this->fileEncryptionKey
            . chr($objNum & 0xFF)
            . chr(($objNum >> 8) & 0xFF)
            . chr(($objNum >> 16) & 0xFF)
            . chr($genNum & 0xFF)
            . chr(($genNum >> 8) & 0xFF)
            . "sAlT";

        $hash = md5($data, true);
        $keyLen = min(16, strlen($this->fileEncryptionKey) + 5);

        return substr($hash, 0, $keyLen);
    }

    private function aesEncrypt(string $data, string $key): string
    {
        $iv = random_bytes(16);
        $cipher = strlen($key) === 32 ? 'aes-256-cbc' : 'aes-128-cbc';
        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

        return $iv . $encrypted;
    }

    private static function createAes128(EncryptionConfig $config, string $fileId, int $permissions): self
    {
        $ownerPassword = $config->ownerPassword !== '' ? $config->ownerPassword : $config->userPassword;
        $userPassword = $config->userPassword;

        // Compute /O value
        $ownerPadded = self::padPassword($ownerPassword);
        $ownerHash = md5($ownerPadded, true);
        for ($i = 0; $i < 50; $i++) {
            $ownerHash = md5($ownerHash, true);
        }
        $ownerKey = substr($ownerHash, 0, 16);

        $userPadded = self::padPassword($userPassword);
        $ownerValue = openssl_encrypt($userPadded, 'aes-128-ecb', $ownerKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

        // RC4-like key rotation for /O (spec requires iterating with modified keys)
        // For AES-128 (V=4 R=4), we use simpler approach: encrypt with each key variant
        for ($i = 1; $i <= 19; $i++) {
            $iterKey = '';
            for ($j = 0; $j < 16; $j++) {
                $iterKey .= chr(ord($ownerKey[$j]) ^ $i);
            }
            $ownerValue = openssl_encrypt($ownerValue, 'aes-128-ecb', $iterKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }
        $ownerValue = substr($ownerValue, 0, 32);

        // Compute file encryption key
        $permBytes = pack('V', $permissions);
        $keyInput = $userPadded . $ownerValue . $permBytes . $fileId;
        $fileKey = md5($keyInput, true);
        for ($i = 0; $i < 50; $i++) {
            $fileKey = md5($fileKey, true);
        }
        $fileKey = substr($fileKey, 0, 16);

        // Compute /U value
        $uInput = self::PADDING . $fileId;
        $uHash = md5($uInput, true);
        $userValue = openssl_encrypt($uHash, 'aes-128-ecb', $fileKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        for ($i = 1; $i <= 19; $i++) {
            $iterKey = '';
            for ($j = 0; $j < 16; $j++) {
                $iterKey .= chr(ord($fileKey[$j]) ^ $i);
            }
            $userValue = openssl_encrypt($userValue, 'aes-128-ecb', $iterKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        }
        // /U is 32 bytes: 16-byte encrypted hash + 16 bytes padding
        $userValue = str_pad($userValue, 32, "\0");

        return new self(
            algorithm: EncryptionAlgorithm::AES128,
            fileEncryptionKey: $fileKey,
            ownerHashValue: $ownerValue,
            userHashValue: $userValue,
            ownerEncKeyValue: '',
            userEncKeyValue: '',
            permsValue: '',
            permissionFlags: $permissions,
        );
    }

    private static function createAes256(EncryptionConfig $config, int $permissions): self
    {
        $ownerPassword = $config->ownerPassword !== '' ? $config->ownerPassword : $config->userPassword;
        $userPassword = $config->userPassword;

        // Generate random 32-byte file encryption key
        $fileKey = random_bytes(32);

        // Generate random salts (each 8 bytes)
        $userValidationSalt = random_bytes(8);
        $userKeySalt = random_bytes(8);
        $ownerValidationSalt = random_bytes(8);
        $ownerKeySalt = random_bytes(8);

        // Compute /U (48 bytes): hash(32) + validationSalt(8) + keySalt(8)
        $userHash = hash('sha256', $userPassword . $userValidationSalt, true);
        $userValue = $userHash . $userValidationSalt . $userKeySalt;

        // Compute /UE (32 bytes): AES-256-CBC encrypt fileKey with hash(password + keySalt)
        $ueKey = hash('sha256', $userPassword . $userKeySalt, true);
        $ueIv = str_repeat("\0", 16);
        $userEncKey = openssl_encrypt($fileKey, 'aes-256-cbc', $ueKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $ueIv);
        $userEncKey = substr($userEncKey, 0, 32);

        // Compute /O (48 bytes): hash(32) + validationSalt(8) + keySalt(8)
        $ownerHash = hash('sha256', $ownerPassword . $ownerValidationSalt . $userValue, true);
        $ownerHashVal = $ownerHash . $ownerValidationSalt . $ownerKeySalt;

        // Compute /OE (32 bytes): AES-256-CBC encrypt fileKey with hash(password + keySalt + /U)
        $oeKey = hash('sha256', $ownerPassword . $ownerKeySalt . $userValue, true);
        $oeIv = str_repeat("\0", 16);
        $ownerEncKey = openssl_encrypt($fileKey, 'aes-256-cbc', $oeKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $oeIv);
        $ownerEncKey = substr($ownerEncKey, 0, 32);

        // Compute /Perms (16 bytes): AES-256-ECB encrypt permission block with fileKey
        $permsBlock = pack('V', $permissions)
            . "\xFF\xFF\xFF\xFF"
            . 'T'
            . 'adb'
            . random_bytes(4);
        $permsValue = openssl_encrypt($permsBlock, 'aes-256-ecb', $fileKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);
        $permsValue = substr($permsValue, 0, 16);

        return new self(
            algorithm: EncryptionAlgorithm::AES256,
            fileEncryptionKey: $fileKey,
            ownerHashValue: $ownerHashVal,
            userHashValue: $userValue,
            ownerEncKeyValue: $ownerEncKey,
            userEncKeyValue: $userEncKey,
            permsValue: $permsValue,
            permissionFlags: $permissions,
        );
    }

    private static function padPassword(string $password): string
    {
        $password = substr($password, 0, 32);
        return str_pad($password, 32, self::PADDING);
    }
}
