<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

final readonly class ProductFilter
{
    public function __construct(
        public ShopifyGid $collectionGid,
        public ProductStatus $status = ProductStatus::Active,
    ) {
    }
}
