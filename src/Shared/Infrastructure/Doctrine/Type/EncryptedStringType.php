<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class EncryptedStringType extends Type
{
    public const NAME = 'encrypted_string';

    private static ?string $key = null;

    public static function configure(string $key): void
    {
        self::$key = $key;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = $this->resolveKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox((string) $value, $nonce, $key);

        return sodium_bin2base64(
            $nonce . $ciphertext,
            SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = $this->resolveKey();
        $decoded = sodium_base642bin((string) $value, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if ($plaintext === false) {
            throw new \RuntimeException('Failed to decrypt value — the encryption key may have changed.');
        }

        return $plaintext;
    }

    private function resolveKey(): string
    {
        if (self::$key === null) {
            throw new \RuntimeException(
                'EncryptedStringType has not been configured. '
                . 'Register EncryptionKeyConfigurator as a Symfony event subscriber.',
            );
        }

        return self::$key;
    }
}
