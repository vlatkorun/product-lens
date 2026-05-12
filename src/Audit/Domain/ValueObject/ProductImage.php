<?php

declare(strict_types=1);

namespace App\Audit\Domain\ValueObject;

final readonly class ProductImage
{
    public function __construct(
        public string $url,
        public ?string $altText,
        public ?int $width,
        public ?int $height,
    ) {
    }
}
