<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Service;

use App\CatalogSync\Domain\Model\Product;
use App\CatalogSync\Domain\ValueObject\ProductFilter;
use App\CatalogSync\Domain\ValueObject\ProductPage;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use App\CatalogSync\Domain\ValueObject\SyncCursor;
use Symfony\Component\Uid\UuidV7;

interface ProductFetcherInterface
{
    public function fetchByGid(ShopifyGid $gid, UuidV7 $tenantId): Product;

    public function fetchPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPage;
}
