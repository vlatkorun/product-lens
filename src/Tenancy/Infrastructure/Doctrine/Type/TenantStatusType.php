<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Doctrine\Type;

use App\Tenancy\Domain\ValueObject\TenantStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TenantStatusType extends Type
{
    public const NAME = 'tenant_status';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'tenant_status';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TenantStatus
    {
        if ($value === null) {
            return null;
        }

        return TenantStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof TenantStatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
