<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\SyncStatus;
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
