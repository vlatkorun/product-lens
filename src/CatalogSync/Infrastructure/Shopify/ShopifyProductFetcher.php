<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Shopify;

use App\CatalogSync\Domain\Model\Product;
use App\CatalogSync\Domain\Service\ProductFetcherInterface;
use App\CatalogSync\Domain\ValueObject\ProductFilter;
use App\CatalogSync\Domain\ValueObject\ProductPage;
use App\CatalogSync\Domain\ValueObject\ProductStatus;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use App\CatalogSync\Domain\ValueObject\SyncCursor;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\Dto\GetProductResponseDto;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\Dto\ImageDto;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\Dto\ProductNodeDto;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\Dto\ProductsByCollectionResponseDto;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\GetProductQuery;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\ProductsByCollectionQuery;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\Uid\UuidV7;

final readonly class ShopifyProductFetcher implements ProductFetcherInterface
{
    public function __construct(
        private ShopifyClient $shopifyClient,
        private TenantRepositoryInterface $tenantRepository,
    ) {
    }

    public function fetchByGid(ShopifyGid $gid, UuidV7 $tenantId): Product
    {
        ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = $this->resolveCredentials($tenantId);

        $data = $this->shopifyClient->query(
            $shopDomain,
            $accessToken,
            GetProductQuery::QUERY,
            GetProductQuery::variables($gid->value),
        );

        $dto = GetProductResponseDto::fromResponse($data);

        return $this->mapProduct($dto->product, $gid, $tenantId, new \DateTimeImmutable());
    }

    public function fetchPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPage {
        ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = $this->resolveCredentials($tenantId);

        $data = $this->shopifyClient->query(
            $shopDomain,
            $accessToken,
            ProductsByCollectionQuery::QUERY,
            ProductsByCollectionQuery::variables(
                $filter->collectionGid->value,
                $pageSize,
                $after?->endCursor,
            ),
        );

        $dto = ProductsByCollectionResponseDto::fromResponse($data);
        $syncedAt = new \DateTimeImmutable();

        $products = \array_values(\array_map(
            fn (ProductNodeDto $node): Product => $this->mapProduct($node, $filter->collectionGid, $tenantId, $syncedAt),
            $dto->products,
        ));

        return new ProductPage(
            $products,
            new SyncCursor(
                $dto->pageInfo->endCursor,
                $dto->pageInfo->hasNextPage,
            ),
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
            $node->featuredImageUrl,
            $syncedAt,
        );
    }
}
