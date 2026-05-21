<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Catalog;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Service\ProductCatalogInterface;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\ProductPage;
use App\Catalog\Domain\ValueObject\ProductPageResult;
use App\Catalog\Domain\ValueObject\ProductResult;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\SyncCursor;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto\GetProductResponseDto;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto\ImageDto;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto\ProductNodeDto;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto\ProductsByCollectionResponseDto;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto\VariantDto;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\GetProductQuery;
use App\Catalog\Infrastructure\Api\GraphQL\Product\Query\ProductsByCollectionQuery;
use App\Catalog\Infrastructure\Api\ShopifyClient;
use App\Shared\Domain\ValueObject\ShopifyGid;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\Uid\UuidV7;

final readonly class ProductCatalog implements ProductCatalogInterface
{
    public function __construct(
        private ShopifyClient $shopifyClient,
        private TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function getByGid(ShopifyGid $gid, UuidV7 $tenantId): ProductResult
    {
        ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = $this->resolveCredentials($tenantId);

        $response = $this->shopifyClient->query(
            $shopDomain,
            $accessToken,
            GetProductQuery::QUERY,
            GetProductQuery::variables($gid->value),
        );

        $dto = GetProductResponseDto::fromResponse($response->data);

        return new ProductResult(
            $this->mapProduct($dto->product, $gid, $tenantId, new \DateTimeImmutable()),
            $response->cost,
            $response->throttle,
        );
    }

    public function getPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPageResult {
        ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = $this->resolveCredentials($tenantId);

        $response = $this->shopifyClient->query(
            $shopDomain,
            $accessToken,
            ProductsByCollectionQuery::QUERY,
            ProductsByCollectionQuery::variables(
                $filter->collectionGid->value,
                $pageSize,
                $after?->endCursor,
            ),
        );

        $dto = ProductsByCollectionResponseDto::fromResponse($response->data);
        $syncedAt = new \DateTimeImmutable();

        $products = \array_values(\array_map(
            fn (ProductNodeDto $node): Product => $this->mapProduct($node, $filter->collectionGid, $tenantId, $syncedAt),
            $dto->products,
        ));

        return new ProductPageResult(
            new ProductPage(
                $products,
                new SyncCursor(
                    $dto->pageInfo->endCursor,
                    $dto->pageInfo->hasNextPage,
                ),
            ),
            $response->cost,
            $response->throttle,
        );
    }

    /** @return array{shopDomain: string, accessToken: string} */
    private function resolveCredentials(UuidV7 $tenantId): array
    {
        $tenant = $this->tenantRepository->findById($tenantId);

        if ($tenant === null || $tenant->shopifyAccessToken() === null) {
            throw new \RuntimeException(\sprintf('No Shopify credentials for tenant %s', $tenantId->toRfc4122()));
        }

        return ['shopDomain' => $tenant->shopDomain(), 'accessToken' => $tenant->shopifyAccessToken()];
    }

    private function mapProduct(ProductNodeDto $node, ShopifyGid $collectionGid, UuidV7 $tenantId, \DateTimeImmutable $syncedAt): Product
    {
        return Product::create(
            $tenantId,
            ShopifyGid::fromString($node->id),
            $collectionGid,
            $node->title,
            $node->handle,
            $node->vendor,
            $node->productType,
            ProductStatus::from(\strtolower($node->status)),
            \array_map(static fn (ImageDto $image): array => $image->toArray(), $node->images),
            \array_map(static fn (VariantDto $variant): array => $variant->toArray(), $node->variants),
            $node->featuredImageUrl,
            $syncedAt,
        );
    }
}
