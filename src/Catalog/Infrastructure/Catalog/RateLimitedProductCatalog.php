<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Catalog;

use App\Catalog\Domain\Service\ProductCatalogInterface;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\ProductPageResult;
use App\Catalog\Domain\ValueObject\ProductResult;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\SyncCursor;
use App\Catalog\Infrastructure\Api\Exception\ShopifyThrottledException;
use App\Catalog\Infrastructure\RateLimit\ShopifyCostEstimator;
use App\Shared\Application\RateLimit\ApiBudgetGuard;
use App\Shared\Domain\RateLimit\BucketId;
use App\Shared\Domain\RateLimit\Exception\RateLimitExceededException;
use App\Shared\Domain\RateLimit\RequestCost;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\Uid\UuidV7;

#[AsDecorator(decorates: ProductCatalog::class)]
final readonly class RateLimitedProductCatalog implements ProductCatalogInterface
{
    public function __construct(
        private ProductCatalogInterface $inner,
        private ApiBudgetGuard $budgetGuard,
        private ShopifyCostEstimator $estimator,
    ) {
    }

    public function getByGid(ShopifyGid $gid, UuidV7 $tenantId): ProductResult
    {
        $bucket = BucketId::shopifyAdmin($tenantId);
        $estimated = $this->estimator->estimateForProductByGid();
        $reservation = $this->budgetGuard->reserve($bucket, $estimated);

        if (!$reservation->granted) {
            throw new RateLimitExceededException($reservation->retryAfterSeconds ?? 1, $bucket);
        }

        try {
            $result = $this->inner->getByGid($gid, $tenantId);
        } catch (ShopifyThrottledException $e) {
            $this->budgetGuard->reconcile($bucket, new RequestCost($estimated, 0));
            throw $e;
        } catch (\Throwable $e) {
            $this->budgetGuard->reconcile($bucket, new RequestCost($estimated, 0));
            throw $e;
        }

        $this->budgetGuard->reconcile($bucket, $result->cost);
        $this->budgetGuard->syncFromResponse($bucket, $result->throttle);

        return $result;
    }

    public function getPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPageResult {
        $bucket = BucketId::shopifyAdmin($tenantId);
        $estimated = $this->estimator->estimateForCollectionPage($pageSize);
        $reservation = $this->budgetGuard->reserve($bucket, $estimated);

        if (!$reservation->granted) {
            throw new RateLimitExceededException($reservation->retryAfterSeconds ?? 1, $bucket);
        }

        try {
            $result = $this->inner->getPage($filter, $tenantId, $after, $pageSize);
        } catch (ShopifyThrottledException $e) {
            $this->budgetGuard->reconcile($bucket, new RequestCost($estimated, 0));
            throw $e;
        } catch (\Throwable $e) {
            $this->budgetGuard->reconcile($bucket, new RequestCost($estimated, 0));
            throw $e;
        }

        $this->budgetGuard->reconcile($bucket, $result->cost);
        $this->budgetGuard->syncFromResponse($bucket, $result->throttle);

        return $result;
    }
}
