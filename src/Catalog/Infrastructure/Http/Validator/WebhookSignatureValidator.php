<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http\Validator;

use Symfony\Component\HttpFoundation\Request;

final readonly class WebhookSignatureValidator
{
    public function validate(Request $request, string $webhookSecret): bool
    {
        $hmac = $request->headers->get('X-Shopify-Hmac-SHA256');
        if ($hmac === null) {
            return false;
        }

        $expected = \base64_encode(\hash_hmac('sha256', (string) $request->getContent(), $webhookSecret, true));

        return \hash_equals($expected, $hmac);
    }
}
