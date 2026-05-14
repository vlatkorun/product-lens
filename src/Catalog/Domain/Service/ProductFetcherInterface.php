<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\ProductPage;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\SyncCursor;
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
