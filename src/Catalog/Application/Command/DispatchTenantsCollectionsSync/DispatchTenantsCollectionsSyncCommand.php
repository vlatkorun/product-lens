<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchTenantsCollectionsSync;

final readonly class DispatchTenantsCollectionsSyncCommand
{
    public function __construct(
        public DispatchTenantsCollectionsSyncCriteriaDto $criteria = new DispatchTenantsCollectionsSyncCriteriaDto(),
    ) {
    }
}
