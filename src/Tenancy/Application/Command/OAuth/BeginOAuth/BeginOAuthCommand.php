<?php

declare(strict_types=1);

namespace App\Tenancy\Application\Command\OAuth\BeginOAuth;

final readonly class BeginOAuthCommand
{
    public function __construct(
        public string $shopDomain,
    ) {}
}
