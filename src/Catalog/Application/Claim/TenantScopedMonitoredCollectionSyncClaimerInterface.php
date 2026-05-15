<?php

declare(strict_types=1);

namespace App\Catalog\Application\Claim;

use App\Catalog\Application\Claim\Dto\ClaimedTenantCollectionSyncDto;
use App\Catalog\Application\Claim\Dto\TenantCollectionSyncClaimCriteriaDto;

interface TenantScopedMonitoredCollectionSyncClaimerInterface
{
    /** @return list<ClaimedTenantCollectionSyncDto> */
    public function claim(TenantCollectionSyncClaimCriteriaDto $criteria): array;
}
