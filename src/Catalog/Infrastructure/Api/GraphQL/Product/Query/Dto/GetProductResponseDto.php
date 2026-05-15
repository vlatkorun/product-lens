<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto;

final readonly class GetProductResponseDto
{
    public function __construct(
        public ProductNodeDto $product,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromResponse(array $data): self
    {
        return new self(
            product: ProductNodeDto::fromNode($data['data']['product']),
        );
    }
}
