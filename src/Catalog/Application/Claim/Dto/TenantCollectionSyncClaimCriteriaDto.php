<?php

declare(strict_types=1);

namespace App\Catalog\Application\Claim\Dto;

final readonly class TenantCollectionSyncClaimCriteriaDto
{
    /** @param list<string> $tenantIds */
    public function __construct(
        public array $tenantIds,
        public ?string $lastCollectionId,
        public int $batchSize,
        public \DateTimeImmutable $now,
    ) {
    }
}
