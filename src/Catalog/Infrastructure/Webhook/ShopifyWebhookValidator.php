<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Webhook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

final readonly class ShopifyWebhookValidator
{
    public function __construct(
        #[Autowire(env: 'SHOPIFY_WEBHOOK_SECRET')]
        private string $webhookSecret,
    ) {
    }

    public function validate(Request $request): bool
    {
        $hmacHeader = $request->headers->get('X-Shopify-Hmac-Sha256');

        if ($hmacHeader === null) {
            return false;
        }

        $expected = \base64_encode(\hash_hmac('sha256', (string) $request->getContent(), $this->webhookSecret, true));

        return \hash_equals($expected, $hmacHeader);
    }
}
