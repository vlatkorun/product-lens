<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\HandleWebhook;

final readonly class HandleWebhookCommand
{
    public function __construct(
        public string $tenantId,
        public string $shopifyProductGid,
        public string $eventType,
    ) {
    }
}
