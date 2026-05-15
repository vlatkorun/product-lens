<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Product;

interface ProductRepositoryInterface
{
    /** @param list<Product> $products */
    public function upsertAll(array $products): void;
}
