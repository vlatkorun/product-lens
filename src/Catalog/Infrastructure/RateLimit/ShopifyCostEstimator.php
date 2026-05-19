<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\RateLimit;

final readonly class ShopifyCostEstimator
{
    public function __construct(
        private int $collectionPageBaseCost,
        private int $productByGidBaseCost,
        private int $costBufferPct,
    ) {
    }

    public function estimateForCollectionPage(int $pageSize): int
    {
        $baseCost = $this->collectionPageBaseCost + (2 * $pageSize);

        return (int) \ceil($baseCost * (1 + $this->costBufferPct / 100));
    }

    public function estimateForProductByGid(): int
    {
        return (int) \ceil($this->productByGidBaseCost * (1 + $this->costBufferPct / 100));
    }
}
