<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Event;

use App\Shared\Domain\Event\DomainEvent;

final readonly class CollectionMonitoringDisabled implements DomainEvent
{
    public function __construct(
        public string $monitoredCollectionId,
        public string $tenantId,
        public string $collectionGid,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
