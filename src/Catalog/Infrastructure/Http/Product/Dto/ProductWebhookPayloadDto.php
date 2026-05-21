<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Product\Dto;

use App\Catalog\Application\Command\HandleWebhook\Dto\WebhookPayloadDtoInterface;
use App\Shared\Domain\ValueObject\ShopifyGid;

final readonly class ProductWebhookPayloadDto implements WebhookPayloadDtoInterface
{
    /** @param list<string> $collectionIds */
    public function __construct(
        public string $id,
        public array $collectionIds,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var list<array{id: int|string}> $collections */
        $collections = $data['collections'] ?? [];

        return new self(
            id: (string) $data['id'],
            collectionIds: \array_values(\array_map(
                static fn (array $c): string => (string) $c['id'],
                $collections,
            )),
        );
    }

    public function gid(): ShopifyGid
    {
        return ShopifyGid::product($this->id);
    }
}
