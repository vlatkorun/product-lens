<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook;

use App\Catalog\Domain\ValueObject\ShopifyWebhookTopic;

final readonly class HandleWebhookCommand
{
    public function __construct(
        public string $tenantId,
        public string $shopifyObjectId,
        public ShopifyWebhookTopic $topic,
        public array $payload,
    ) {
    }
}
