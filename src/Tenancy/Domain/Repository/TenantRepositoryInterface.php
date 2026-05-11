<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Repository;

use App\Tenancy\Domain\Model\Tenant;

interface TenantRepositoryInterface
{
    public function save(Tenant $tenant): void;

    public function findByShopDomain(string $shopDomain): ?Tenant;
}
