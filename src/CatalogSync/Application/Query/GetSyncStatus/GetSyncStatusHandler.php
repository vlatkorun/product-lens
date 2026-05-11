<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Query\GetSyncStatus;

use App\CatalogSync\Domain\Model\SyncJob;
use App\CatalogSync\Domain\Repository\SyncJobRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class GetSyncStatusHandler
{
    public function __construct(private SyncJobRepositoryInterface $syncJobRepository)
    {
    }

    public function __invoke(GetSyncStatusQuery $query): ?SyncJob
    {
        return $this->syncJobRepository->findById(UuidV7::fromString($query->syncJobId));
    }
}
