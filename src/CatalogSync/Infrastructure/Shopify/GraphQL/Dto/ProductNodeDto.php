<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Shopify\GraphQL\Dto;

final readonly class ProductNodeDto
{
    /** @param list<ImageDto> $images */
    public function __construct(
        public string $id,
        public string $title,
        public string $handle,
        public string $vendor,
        public string $productType,
        public string $status,
        public ?string $featuredImageUrl,
        public array $images,
    ) {
    }

    /** @param array<string, mixed> $node */
    public static function fromNode(array $node): self
    {
        $images = \array_values(\array_map(
            static fn (array $edge): ImageDto => ImageDto::fromNode($edge['node']),
            $node['images']['edges'],
        ));

        return new self(
            id: $node['id'],
            title: $node['title'],
            handle: $node['handle'],
            vendor: $node['vendor'] ?? '',
            productType: $node['productType'] ?? '',
            status: $node['status'],
            featuredImageUrl: $node['featuredImage']['url'] ?? null,
            images: $images,
        );
    }
}
