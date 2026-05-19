<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Api\GraphQL\Product\Query\Dto;

use App\Shared\Domain\RateLimit\RequestCost;
use App\Shared\Domain\RateLimit\ThrottleStatus;

final readonly class ApiCostDto
{
    public function __construct(
        public int $requestedQueryCost,
        public int $actualQueryCost,
        public ThrottleStatus $throttleStatus,
    ) {
    }

    /**
     * @param array{
     *   requestedQueryCost: int|float,
     *   actualQueryCost: int|float,
     *   throttleStatus: array{maximumAvailable: int|float, currentlyAvailable: int|float, restoreRate: int|float}
     * } $cost
     */
    public static function fromExtensionsCost(array $cost): self
    {
        return new self(
            (int) $cost['requestedQueryCost'],
            (int) $cost['actualQueryCost'],
            new ThrottleStatus(
                (int) $cost['throttleStatus']['maximumAvailable'],
                (int) $cost['throttleStatus']['currentlyAvailable'],
                (int) $cost['throttleStatus']['restoreRate'],
            ),
        );
    }

    public function toRequestCost(): RequestCost
    {
        return new RequestCost($this->requestedQueryCost, $this->actualQueryCost);
    }
}
