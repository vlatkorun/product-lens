<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Shopify\GraphQL\Dto;

final readonly class VariantDto
{
    public function __construct(
        public string $id,
        public string $title,
        public ?ImageDto $image,
    ) {
    }

    /** @param array{id: string, title: string, image: ?array{url: string, altText: ?string, width: ?int, height: ?int}} $node */
    public static function fromNode(array $node): self
    {
        return new self(
            id: $node['id'],
            title: $node['title'],
            image: isset($node['image']) ? ImageDto::fromNode($node['image']) : null,
        );
    }

    /** @return array{id: string, title: string, image: ?array{url: string, altText: ?string, width: ?int, height: ?int}} */
    public function toArray(): array
    {
        return [
            'id'    => $this->id,
            'title' => $this->title,
            'image' => $this->image?->toArray(),
        ];
    }
}
