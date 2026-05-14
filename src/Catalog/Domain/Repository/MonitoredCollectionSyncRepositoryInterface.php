<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Repository;

use App\CatalogSync\Domain\Model\MonitoredCollectionSync;
use Symfony\Component\Uid\UuidV7;

interface MonitoredCollectionSyncRepositoryInterface
{
    public function save(MonitoredCollectionSync $job): void;

    public function findById(UuidV7 $id): ?MonitoredCollectionSync;

    /**
     * Loads the job after acquiring a row-level write lock via `FOR UPDATE SKIP LOCKED`.
     * Returns null if the row is already locked by another worker or does not exist.
     * Caller MUST already be inside a DB transaction so the lock survives until commit.
     */
    public function findByIdForProcessing(UuidV7 $id): ?MonitoredCollectionSync;

    /** @return list<MonitoredCollectionSync> */
    public function findStuckPending(\DateTimeImmutable $olderThan): array;
}
