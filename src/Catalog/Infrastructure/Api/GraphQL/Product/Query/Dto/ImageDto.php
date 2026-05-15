<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto;

final readonly class ImageDto
{
    public function __construct(
        public string $url,
        public ?string $altText,
        public ?int $width,
        public ?int $height,
    ) {
    }

    /** @param array{url: string, altText: ?string, width: ?int, height: ?int} $node */
    public static function fromNode(array $node): self
    {
        return new self(
            url: $node['url'],
            altText: $node['altText'] ?? null,
            width: $node['width'] ?? null,
            height: $node['height'] ?? null,
        );
    }

    /** @return array{url: string, altText: ?string, width: ?int, height: ?int} */
    public function toArray(): array
    {
        return [
            'url'     => $this->url,
            'altText' => $this->altText,
            'width'   => $this->width,
            'height'  => $this->height,
        ];
    }
}
