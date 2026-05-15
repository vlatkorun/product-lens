<?php

declare(strict_types=1);

namespace App\Catalog\Application\Claim\Dto;

final readonly class ActiveTenantBatchCriteriaDto
{
    public function __construct(
        public ?string $lastTenantId,
        public int $batchSize,
    ) {
    }
}
