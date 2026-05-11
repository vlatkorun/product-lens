<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Repository;

use App\CatalogSync\Domain\Model\Product;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

interface ProductRepositoryInterface
{
    /** @param list<Product> $products */
    public function upsertAll(array $products): void;

    public function upsert(Product $product): void;

    public function remove(ShopifyGid $shopifyGid, UuidV7 $tenantId): void;
}
