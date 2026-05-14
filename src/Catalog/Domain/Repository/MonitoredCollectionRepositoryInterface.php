<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\MonitoredCollection;
use App\Catalog\Domain\ValueObject\ShopifyGid;
use Symfony\Component\Uid\UuidV7;

interface MonitoredCollectionRepositoryInterface
{
    public function save(MonitoredCollection $collection): void;

    public function findById(UuidV7 $id): ?MonitoredCollection;

    public function findByTenantAndCollectionGid(UuidV7 $tenantId, ShopifyGid $collectionGid): ?MonitoredCollection;
}
