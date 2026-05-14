<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Persistence;

use App\CatalogSync\Domain\Model\Product;
use App\CatalogSync\Domain\Repository\ProductRepositoryInterface;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @param list<Product> $products */
    public function upsertAll(array $products): void
    {
        foreach ($products as $product) {
            $this->upsert($product);
        }
    }

    public function upsert(Product $product): void
    {
        $conn = $this->getEntityManager()->getConnection();

        $conn->executeStatement(
            <<<'SQL'
                INSERT INTO products
                    (resource_id, shopify_gid, tenant_id, collection_gid, title, handle, vendor, product_type, status, images, featured_image_url, synced_at)
                VALUES
                    (:resource_id, :shopify_gid, :tenant_id, :collection_gid, :title, :handle, :vendor, :product_type, :status, :images, :featured_image_url, :synced_at)
                ON CONFLICT (tenant_id, shopify_gid) DO UPDATE SET
                    collection_gid     = EXCLUDED.collection_gid,
                    title              = EXCLUDED.title,
                    handle             = EXCLUDED.handle,
                    vendor             = EXCLUDED.vendor,
                    product_type       = EXCLUDED.product_type,
                    status             = EXCLUDED.status,
                    images             = EXCLUDED.images,
                    featured_image_url = EXCLUDED.featured_image_url,
                    synced_at          = EXCLUDED.synced_at
                SQL,
            [
                'resource_id'        => $product->id()->toRfc4122(),
                'shopify_gid'        => $product->shopifyGid()->value,
                'tenant_id'          => $product->tenantId()->toRfc4122(),
                'collection_gid'     => $product->collectionGid()->value,
                'title'              => $product->title(),
                'handle'             => $product->handle(),
                'vendor'             => $product->vendor(),
                'product_type'       => $product->productType(),
                'status'             => $product->status()->value,
                'images'             => \json_encode($product->images(), \JSON_THROW_ON_ERROR),
                'featured_image_url' => $product->featuredImageUrl(),
                'synced_at'          => $product->syncedAt()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function remove(ShopifyGid $shopifyGid, UuidV7 $tenantId): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'DELETE FROM products WHERE shopify_gid = ? AND tenant_id = ?',
            [$shopifyGid->value, $tenantId->toRfc4122()],
        );
    }
}
