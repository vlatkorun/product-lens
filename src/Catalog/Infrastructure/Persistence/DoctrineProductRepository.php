<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /** @param list<Product> $products */
    public function upsertAll(array $products): void
    {
        if ($products === []) {
            return;
        }

        $conn = $this->getEntityManager()->getConnection();

        foreach ($products as $product) {
            $conn->executeStatement(
                <<<'SQL'
                    INSERT INTO products
                        (resource_id, shopify_gid, tenant_id, collection_gid, title, handle, vendor, product_type,
                         status, images, variants, featured_image_url, synced_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::jsonb, ?::jsonb, ?, ?)
                    ON CONFLICT (tenant_id, shopify_gid) DO UPDATE SET
                        collection_gid    = EXCLUDED.collection_gid,
                        title             = EXCLUDED.title,
                        handle            = EXCLUDED.handle,
                        vendor            = EXCLUDED.vendor,
                        product_type      = EXCLUDED.product_type,
                        status            = EXCLUDED.status,
                        images            = EXCLUDED.images,
                        variants          = EXCLUDED.variants,
                        featured_image_url = EXCLUDED.featured_image_url,
                        synced_at         = EXCLUDED.synced_at
                    SQL,
                [
                    $product->id()->toRfc4122(),
                    $product->shopifyGid()->value,
                    $product->tenantId()->toRfc4122(),
                    $product->collectionGid()->value,
                    $product->title(),
                    $product->handle(),
                    $product->vendor(),
                    $product->productType(),
                    $product->status()->value,
                    \json_encode($product->images(), \JSON_THROW_ON_ERROR),
                    \json_encode($product->variants(), \JSON_THROW_ON_ERROR),
                    $product->featuredImageUrl(),
                    $product->syncedAt()->format('Y-m-d H:i:s'),
                ],
            );
        }
    }
}
