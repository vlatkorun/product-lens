<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\AsyncDomainEvent;

final readonly class MonitoredCollectionSyncProcessed implements AsyncDomainEvent
{
    public function __construct(
        public string $syncJobId,
        public int $pageCount,
        public int $totalProcessed,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
