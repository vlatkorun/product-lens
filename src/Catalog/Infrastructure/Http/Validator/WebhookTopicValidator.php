<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Validator;

use App\Catalog\Domain\ValueObject\ShopifyWebhookTopic;
use Symfony\Component\HttpFoundation\Request;

final readonly class WebhookTopicValidator
{
    public function validate(Request $request, string $topicPrefix): bool
    {
        $topic = ShopifyWebhookTopic::tryFrom($request->headers->get('X-Shopify-Topic', ''));

        return $topic?->isFor($topicPrefix) ?? false;
    }
}
