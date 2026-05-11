<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Type;

use App\Identity\Domain\ValueObject\UserRole;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class UserRoleType extends Type
{
    public const NAME = 'user_role';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'user_role';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?UserRole
    {
        if ($value === null) {
            return null;
        }

        return UserRole::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UserRole) {
            return $value->value;
        }

        return (string) $value;
    }
}
