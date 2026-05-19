<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\ProductPageResult;
use App\Catalog\Domain\ValueObject\ProductResult;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\SyncCursor;
use Symfony\Component\Uid\UuidV7;

interface ProductCatalogInterface
{
    public function getByGid(ShopifyGid $gid, UuidV7 $tenantId): ProductResult;

    public function getPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPageResult;
}
