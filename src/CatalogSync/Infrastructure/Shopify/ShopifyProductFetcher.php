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
use App\CatalogSync\Infrastructure\Shopify\GraphQL\GetProductQuery;
use App\CatalogSync\Infrastructure\Shopify\GraphQL\ProductsByCollectionQuery;
use App\Tenancy\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\Uid\UuidV7;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ShopifyProductFetcher implements ProductFetcherInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    public function fetchByGid(ShopifyGid $gid, UuidV7 $tenantId): Product
    {
        ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = $this->resolveCredentials($tenantId);

        $response = $this->query(
            $shopDomain,
            $accessToken,
            GetProductQuery::QUERY,
            GetProductQuery::variables($gid->value),
        );

        $node = $response['data']['product'];

        return $this->mapProduct($node, $gid, $tenantId, new \DateTimeImmutable());
    }

    public function fetchPage(
        ProductFilter $filter,
        UuidV7 $tenantId,
        ?SyncCursor $after = null,
        int $pageSize = 250,
    ): ProductPage {
        ['shopDomain' => $shopDomain, 'accessToken' => $accessToken] = $this->resolveCredentials($tenantId);

        $response = $this->query(
            $shopDomain,
            $accessToken,
            ProductsByCollectionQuery::QUERY,
            ProductsByCollectionQuery::variables(
                $filter->collectionGid->value,
                $pageSize,
                $after?->endCursor,
            ),
        );

        $connection = $response['data']['collection']['products'];
        $syncedAt = new \DateTimeImmutable();

        $products = \array_map(
            fn(array $edge): Product => $this->mapProduct(
                $edge['node'],
                $filter->collectionGid,
                $tenantId,
                $syncedAt,
            ),
            $connection['edges'],
        );

        return new ProductPage(
            $products,
            new SyncCursor(
                $connection['pageInfo']['endCursor'],
                $connection['pageInfo']['hasNextPage'],
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

    private function query(string $shopDomain, string $accessToken, string $query, array $variables): array
    {
        $response = $this->httpClient->request('POST', \sprintf('https://%s/admin/api/2024-10/graphql.json', $shopDomain), [
            'headers' => [
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type'           => 'application/json',
            ],
            'json' => ['query' => $query, 'variables' => $variables],
        ]);

        $data = $response->toArray();

        if (!empty($data['errors'])) {
            throw new \RuntimeException(\sprintf('Shopify GraphQL error: %s', \json_encode($data['errors'])));
        }

        return $data;
    }

    private function mapProduct(array $node, ShopifyGid $collectionGid, UuidV7 $tenantId, \DateTimeImmutable $syncedAt): Product
    {
        $images = \array_map(
            static fn(array $edge): array => [
                'url'     => $edge['node']['url'],
                'altText' => $edge['node']['altText'],
                'width'   => $edge['node']['width'],
                'height'  => $edge['node']['height'],
            ],
            $node['images']['edges'],
        );

        return Product::create(
            $tenantId,
            ShopifyGid::fromString($node['id']),
            $collectionGid,
            $node['title'],
            $node['handle'],
            $node['vendor'] ?? '',
            $node['productType'] ?? '',
            ProductStatus::from(\strtolower($node['status'])),
            $images,
            $node['featuredImage']['url'] ?? null,
            $syncedAt,
        );
    }
}
