<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Service\OAuth;

use App\Tenancy\Domain\ValueObject\ShopifyTokenResult;

interface ShopifyOAuthClientInterface
{
    public function exchangeCodeForToken(string $shopDomain, string $code): ShopifyTokenResult;
}
