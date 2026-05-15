<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\AcquireTenantsCollectionsForSync;

final readonly class AcquireTenantsCollectionsForSyncCommand
{
    /** @param list<string> $tenantIds */
    public function __construct(
        public array $tenantIds,
        public ?string $lastCollectionId = null,
    ) {
    }
}
