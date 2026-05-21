<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook\Dto;

use App\Shared\Domain\ValueObject\ShopifyGid;

interface WebhookPayloadDtoInterface
{
    public function gid(): ShopifyGid;
}
