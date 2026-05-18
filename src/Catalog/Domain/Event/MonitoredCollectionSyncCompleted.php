<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\AsyncDomainEvent;
use App\Shared\Domain\ValueObject\AuditCheck;

final readonly class MonitoredCollectionSyncCompleted implements AsyncDomainEvent
{
    /** @param list<AuditCheck> $auditChecks */
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
        public string $collectionGid,
        public array $auditChecks,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
