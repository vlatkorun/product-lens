<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use Symfony\Component\Uid\UuidV7;

final readonly class AuditableProduct
{
    /** @param list<ProductImage> $images */
    public function __construct(
        private UuidV7 $productId,
        private UuidV7 $tenantId,
        private string $title,
        private array $images,
    ) {
    }

    public function productId(): UuidV7
    {
        return $this->productId;
    }

    public function tenantId(): UuidV7
    {
        return $this->tenantId;
    }

    public function title(): string
    {
        return $this->title;
    }

    /** @return list<ProductImage> */
    public function images(): array
    {
        return $this->images;
    }

    public function hasImages(): bool
    {
        return $this->images !== [];
    }
}
