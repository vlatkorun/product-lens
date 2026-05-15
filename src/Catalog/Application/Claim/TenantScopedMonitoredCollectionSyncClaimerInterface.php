<?php

declare(strict_types=1);

namespace App\Catalog\Application\Claim;

interface TenantScopedMonitoredCollectionSyncClaimerInterface
{
    /** @return list<ClaimedTenantCollectionSyncDto> */
    public function claim(TenantCollectionSyncClaimCriteriaDto $criteria): array;
}
