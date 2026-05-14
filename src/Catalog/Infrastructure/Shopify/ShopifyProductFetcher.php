<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Shopify;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Service\ProductFetcherInterface;
use App\Catalog\Domain\ValueObject\ProductFilter;
use App\Catalog\Domain\ValueObject\ProductPage;
use App\Catalog\Domain\ValueObject\ProductStatus;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use App\Catalog\Domain\ValueObject\SyncCursor;
use App\Catalog\Infrastructure\Shopify\GraphQL\Dto\GetProductResponseDto;
use App\Catalog\Infrastructure\Shopify\GraphQL\Dto\ImageDto;
use App\Catalog\Infrastructure\Shopify\GraphQL\Dto\ProductNodeDto;
use App\Catalog\Infrastructure\Shopify\GraphQL\Dto\ProductsByCollectionResponseDto;
use App\Catalog\Infrastructure\Shopify\GraphQL\Dto\VariantDto;
use App\Catalog\Infrastructure\Shopify\GraphQL\GetProductQuery;
use App\Catalog\Infrastructure\Shopify\GraphQL\ProductsByCollectionQuery;
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
            \array_map(static fn (VariantDto $variant): array => $variant->toArray(), $node->variants),
            $node->featuredImageUrl,
            $syncedAt,
        );
    }
}
