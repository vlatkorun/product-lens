<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Model;

use App\CatalogSync\Domain\ValueObject\ProductStatus;
use App\CatalogSync\Domain\ValueObject\ShopifyGid;
use App\CatalogSync\Infrastructure\Persistence\DoctrineProductRepository;
use App\Shared\Domain\Model\TenantScopedInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: DoctrineProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'products_tenant_shopify_gid_uq', columns: ['tenant_id', 'shopify_gid'])]
#[ORM\Index(name: 'products_tenant_id_idx', columns: ['tenant_id'])]
#[ORM\Index(name: 'products_synced_at_idx', columns: ['synced_at'])]
class Product implements TenantScopedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    private UuidV7 $id;

    #[ORM\Column(name: 'shopify_gid', length: 255)]
    private string $shopifyGidRaw;

    #[ORM\Column(name: 'tenant_id', type: 'uuid')]
    private UuidV7 $tenantId;

    #[ORM\Column(name: 'collection_gid', length: 255)]
    private string $collectionGidRaw;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 255)]
    private string $handle;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $vendor;

    #[ORM\Column(name: 'product_type', length: 255, options: ['default' => ''])]
    private string $productType;

    #[ORM\Column(type: 'product_status')]
    private ProductStatus $status;

    /** @var list<array{url: string, altText: ?string, width: ?int, height: ?int}> */
    #[ORM\Column(type: 'jsonb', options: ['default' => '[]'])]
    private array $images;

    #[ORM\Column(name: 'featured_image_url', type: 'text', nullable: true)]
    private ?string $featuredImageUrl;

    #[ORM\Column(name: 'synced_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $syncedAt;

    private function __construct() {}

    /**
     * @param list<array{url: string, altText: ?string, width: ?int, height: ?int}> $images
     */
    public static function create(
        UuidV7 $tenantId,
        ShopifyGid $shopifyGid,
        ShopifyGid $collectionGid,
        string $title,
        string $handle,
        string $vendor,
        string $productType,
        ProductStatus $status,
        array $images,
        ?string $featuredImageUrl,
        \DateTimeImmutable $syncedAt,
    ): self {
        $product = new self();
        $product->id = new UuidV7();
        $product->tenantId = $tenantId;
        $product->shopifyGidRaw = $shopifyGid->value;
        $product->collectionGidRaw = $collectionGid->value;
        $product->title = $title;
        $product->handle = $handle;
        $product->vendor = $vendor;
        $product->productType = $productType;
        $product->status = $status;
        $product->images = $images;
        $product->featuredImageUrl = $featuredImageUrl;
        $product->syncedAt = $syncedAt;

        return $product;
    }

    public function id(): UuidV7
    {
        return $this->id;
    }

    public function shopifyGid(): ShopifyGid
    {
        return ShopifyGid::fromString($this->shopifyGidRaw);
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }

    public function collectionGid(): ShopifyGid
    {
        return ShopifyGid::fromString($this->collectionGidRaw);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function vendor(): string
    {
        return $this->vendor;
    }

    public function productType(): string
    {
        return $this->productType;
    }

    public function status(): ProductStatus
    {
        return $this->status;
    }

    /** @return list<array{url: string, altText: ?string, width: ?int, height: ?int}> */
    public function images(): array
    {
        return $this->images;
    }

    public function featuredImageUrl(): ?string
    {
        return $this->featuredImageUrl;
    }

    public function syncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }
}
