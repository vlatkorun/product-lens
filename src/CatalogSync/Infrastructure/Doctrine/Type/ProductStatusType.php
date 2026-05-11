<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Doctrine\Type;

use App\CatalogSync\Domain\ValueObject\ProductStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ProductStatusType extends Type
{
    public const NAME = 'product_status';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'product_status';
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProductStatus
    {
        if ($value === null) {
            return null;
        }

        return ProductStatus::from((string) $value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof ProductStatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
