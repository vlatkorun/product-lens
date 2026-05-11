<?php

declare(strict_types=1);

namespace App\Tenancy\Application\Command\OAuth\CompleteOAuth;

final readonly class CompleteOAuthCommand
{
    /**
     * @param array<string, string> $queryParams all query params from the callback URL, used for HMAC validation
     */
    public function __construct(
        public string $shopDomain,
        public string $code,
        public string $state,
        public string $hmac,
        public array $queryParams,
    ) {}
}
