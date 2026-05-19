<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Shared\Domain\RateLimit\RequestCost;
use App\Shared\Domain\RateLimit\ThrottleStatus;

final readonly class ProductPageResult
{
    public function __construct(
        public ProductPage $page,
        public RequestCost $cost,
        public ThrottleStatus $throttle,
    ) {
    }
}
