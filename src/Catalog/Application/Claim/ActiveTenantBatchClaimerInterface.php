<?php

declare(strict_types=1);

namespace App\Catalog\Application\Claim;

interface ActiveTenantBatchClaimerInterface
{
    /** @return list<string> */
    public function claim(ActiveTenantBatchCriteriaDto $criteria): array;
}
