<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\HandleWebhook\Dto;

interface WebhookPayloadDtoInterface
{
    public function gid(): string;
}
