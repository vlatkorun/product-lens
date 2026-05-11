<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Service\OAuth;

interface ShopifyHmacValidatorInterface
{
    /**
     * @param array<string, string> $queryParams all query params including hmac
     */
    public function validate(string $hmac, array $queryParams): bool;
}
