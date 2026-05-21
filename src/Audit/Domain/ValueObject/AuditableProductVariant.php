<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use App\Shared\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

final readonly class AuditableProductVariant extends AuditableObject
{
    public function __construct(
        ShopifyGid $gid,
        private ShopifyGid $productGid,
        UuidV7 $tenantId,
        private string $title,
        private ?ProductImage $image,
    ) {
        parent::__construct($gid, $tenantId);
    }

    public function productGid(): ShopifyGid
    {
        return $this->productGid;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function image(): ?ProductImage
    {
        return $this->image;
    }

    public function hasImage(): bool
    {
        return $this->image !== null;
    }
}
