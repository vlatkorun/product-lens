<?php

declare(strict_types=1);

namespace App\Catalog\Application\Claim\Dto;

final readonly class ClaimedTenantCollectionSyncDto
{
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
        public string $monitoredCollectionId,
    ) {
    }
}
