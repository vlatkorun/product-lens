<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto;

final readonly class ProductsByCollectionResponseDto
{
    /** @param list<ProductNodeDto> $products */
    public function __construct(
        public array $products,
        public PageInfoDto $pageInfo,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromResponse(array $data): self
    {
        $connection = $data['data']['collection']['products'];

        $products = \array_values(\array_map(
            static fn (array $edge): ProductNodeDto => ProductNodeDto::fromNode($edge['node']),
            $connection['edges'],
        ));

        return new self(
            products: $products,
            pageInfo: new PageInfoDto(
                hasNextPage: $connection['pageInfo']['hasNextPage'],
                endCursor: $connection['pageInfo']['endCursor'],
            ),
        );
    }
}
