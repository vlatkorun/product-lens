<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\Exception;

final class ShopifyThrottledException extends \RuntimeException
{
    public function __construct(string $shopDomain)
    {
        parent::__construct(\sprintf('Shopify throttled the request for shop: %s', $shopDomain));
    }
}
