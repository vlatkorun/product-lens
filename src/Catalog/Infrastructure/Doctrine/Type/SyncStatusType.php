<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Doctrine\Type;

use App\CatalogSync\Domain\ValueObject\SyncStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class SyncStatusType extends Type
{
    public const NAME = 'sync_status';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'sync_status';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?SyncStatus
    {
        if ($value === null) {
            return null;
        }

        return SyncStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SyncStatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
