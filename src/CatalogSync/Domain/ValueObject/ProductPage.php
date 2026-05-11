<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\ValueObject;

use App\CatalogSync\Domain\Model\Product;

final readonly class ProductPage
{
    /** @param list<Product> $products */
    public function __construct(
        public array $products,
        public SyncCursor $cursor,
    ) {
    }
}
