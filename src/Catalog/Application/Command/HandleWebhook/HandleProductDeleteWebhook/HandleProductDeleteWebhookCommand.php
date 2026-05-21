<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook\HandleProductDeleteWebhook;

use App\Catalog\Infrastructure\Http\Product\Dto\ProductWebhookPayloadDto;

final readonly class HandleProductDeleteWebhookCommand
{
    public function __construct(
        public string $tenantId,
        public ProductWebhookPayloadDto $payload,
    ) {
    }
}
