<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Repository;

use App\CatalogSync\Domain\Model\SyncJob;
use Symfony\Component\Uid\UuidV7;

interface SyncJobRepositoryInterface
{
    public function save(SyncJob $job): void;

    public function findById(UuidV7 $id): ?SyncJob;

    /**
     * Loads the job after acquiring a row-level write lock via `FOR UPDATE SKIP LOCKED`.
     * Returns null if the row is already locked by another worker or does not exist.
     * Caller MUST already be inside a DB transaction so the lock survives until commit.
     */
    public function findByIdForProcessing(UuidV7 $id): ?SyncJob;

    /** @return list<SyncJob> */
    public function findStuckPending(\DateTimeImmutable $olderThan): array;
}
