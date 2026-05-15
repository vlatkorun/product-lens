<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto;

final readonly class ProductNodeDto
{
    /**
     * @param list<ImageDto> $images
     * @param list<VariantDto> $variants
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $handle,
        public string $vendor,
        public string $productType,
        public string $status,
        public ?string $featuredImageUrl,
        public array $images,
        public array $variants,
    ) {
    }

    /** @param array<string, mixed> $node */
    public static function fromNode(array $node): self
    {
        $images = \array_values(\array_map(
            static fn (array $edge): ImageDto => ImageDto::fromNode($edge['node']),
            $node['images']['edges'],
        ));

        $variants = \array_values(\array_map(
            static fn (array $edge): VariantDto => VariantDto::fromNode($edge['node']),
            $node['variants']['edges'],
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
            variants: $variants,
        );
    }
}
