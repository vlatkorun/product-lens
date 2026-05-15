<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchTenantsCollectionsSync;

final readonly class DispatchTenantsCollectionsSyncCriteriaDto
{
    /** @param list<string> $tenantIds */
    public function __construct(
        public array $tenantIds = [],
        public ?string $lastTenantId = null,
    ) {
    }
}
