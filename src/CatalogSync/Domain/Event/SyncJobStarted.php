<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Event;

use App\Shared\Domain\Event\AsyncDomainEvent;

final readonly class SyncJobStarted implements AsyncDomainEvent
{
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
        public string $collectionGid,
        public \DateTimeImmutable $occurredAt,
    ) {}
}
