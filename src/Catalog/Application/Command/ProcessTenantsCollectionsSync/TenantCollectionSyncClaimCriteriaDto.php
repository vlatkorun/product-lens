<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantsCollectionsSync;

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
