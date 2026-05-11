<?php

declare(strict_types=1);

namespace App\Tenancy\Domain\Repository;

use App\Tenancy\Domain\Model\Tenant;
use Symfony\Component\Uid\UuidV7;

interface TenantRepositoryInterface
{
    public function save(Tenant $tenant): void;

    public function findById(UuidV7 $id): ?Tenant;

    public function findByShopDomain(string $shopDomain): ?Tenant;
}
