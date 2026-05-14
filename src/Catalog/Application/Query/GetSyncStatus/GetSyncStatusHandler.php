<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetSyncStatus;

use App\Catalog\Domain\Model\MonitoredCollectionSync;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class GetSyncStatusHandler
{
    public function __construct(private MonitoredCollectionSyncRepositoryInterface $syncJobRepository)
    {
    }

    public function __invoke(GetSyncStatusQuery $query): ?MonitoredCollectionSync
    {
        return $this->syncJobRepository->findById(UuidV7::fromString($query->syncJobId));
    }
}
