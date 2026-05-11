<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Shopify\GraphQL\Dto;

final readonly class PageInfoDto
{
    public function __construct(
        public bool $hasNextPage,
        public ?string $endCursor,
    ) {
    }
}
