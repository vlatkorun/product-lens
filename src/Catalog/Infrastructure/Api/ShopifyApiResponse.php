<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api;

use App\Shared\Domain\RateLimit\RequestCost;
use App\Shared\Domain\RateLimit\ThrottleStatus;

final readonly class ShopifyApiResponse
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public array $data,
        public RequestCost $cost,
        public ThrottleStatus $throttle,
    ) {
    }
}
