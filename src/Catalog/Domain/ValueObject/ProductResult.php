<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Model\Product;
use App\Shared\Domain\RateLimit\RequestCost;
use App\Shared\Domain\RateLimit\ThrottleStatus;

final readonly class ProductResult
{
    public function __construct(
        public Product $product,
        public RequestCost $cost,
        public ThrottleStatus $throttle,
    ) {
    }
}
