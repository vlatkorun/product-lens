<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Type;

use App\Identity\Domain\ValueObject\UserStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UserStatusType extends Type
{
    public const NAME = 'user_status';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'user_status';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserStatus
    {
        if ($value === null) {
            return null;
        }

        return UserStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UserStatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
