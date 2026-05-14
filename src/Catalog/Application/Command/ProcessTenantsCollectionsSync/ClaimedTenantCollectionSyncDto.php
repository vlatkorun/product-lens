<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantsCollectionsSync;

final readonly class ClaimedTenantCollectionSyncDto
{
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
        public string $monitoredCollectionId,
    ) {
    }
}
