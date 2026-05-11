<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Shopify;

use App\Tenancy\Domain\Service\OAuth\ShopifyHmacValidatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class ShopifyHmacValidator implements ShopifyHmacValidatorInterface
{
    public function __construct(
        #[Autowire('%env(SHOPIFY_API_SECRET)%')]
        private string $apiSecret,
    ) {
    }

    /**
     * @param array<string, string> $queryParams all query params including hmac
     */
    public function validate(string $hmac, array $queryParams): bool
    {
        $params = $queryParams;
        unset($params['hmac']);
        \ksort($params);

        $message = \implode('&', \array_map(
            static fn (string $key, string $value): string => $key . '=' . \str_replace(
                ['%', '&'],
                ['%25', '%26'],
                $value,
            ),
            \array_keys($params),
            \array_values($params),
        ));

        $computed = \hash_hmac('sha256', $message, $this->apiSecret);

        return \hash_equals($computed, $hmac);
    }
}
