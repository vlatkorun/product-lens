<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook\HandleProductUpdateWebhook;

use App\Catalog\Infrastructure\Http\Product\Dto\ProductWebhookPayloadDto;

final readonly class HandleProductUpdateWebhookCommand
{
    public function __construct(
        public string $tenantId,
        public ProductWebhookPayloadDto $payload,
    ) {
    }
}
