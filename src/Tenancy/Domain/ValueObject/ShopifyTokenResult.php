<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\ValueObject;

final readonly class ShopifyTokenResult
{
    public function __construct(
        public string $accessToken,
        public string $scope,
    ) {}
}
