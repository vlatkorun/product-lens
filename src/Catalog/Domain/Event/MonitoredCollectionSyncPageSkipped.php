<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Event;

use App\CatalogSync\Domain\ValueObject\SyncStatus;
use App\Shared\Domain\Event\AsyncDomainEvent;

final readonly class MonitoredCollectionSyncPageSkipped implements AsyncDomainEvent
{
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
        public SyncStatus $actualStatus,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
