<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

use Symfony\Component\Uid\UuidV7;

final readonly class AuditableProductVariant extends AuditableObject
{
    public function __construct(
        UuidV7 $variantId,
        private UuidV7 $productId,
        UuidV7 $tenantId,
        private string $title,
        private ?ProductImage $image,
    ) {
        parent::__construct($variantId, $tenantId);
    }

    public function productId(): UuidV7
    {
        return $this->productId;
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
