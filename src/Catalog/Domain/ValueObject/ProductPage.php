<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Model\Product;

final readonly class ProductPage
{
    /** @param list<Product> $products */
    public function __construct(
        public array $products,
        public SyncCursor $cursor,
    ) {
    }
}
