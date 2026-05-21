<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

final readonly class AuditableProduct extends AuditableObject
{
    /** @param list<ProductImage> $images */
    public function __construct(
        ShopifyGid $gid,
        UuidV7 $tenantId,
        private string $title,
        private array $images,
    ) {
        parent::__construct($gid, $tenantId);
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
