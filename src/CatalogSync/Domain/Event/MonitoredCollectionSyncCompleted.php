<?php

declare(strict_types=1);

namespace App\CatalogSync\Domain\Event;

use App\Shared\Domain\Event\AsyncDomainEvent;
use App\Shared\Domain\ValueObject\FeatureFlag;

final readonly class MonitoredCollectionSyncCompleted implements AsyncDomainEvent
{
    /** @param list<FeatureFlag> $featureFlags */
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
        public string $collectionGid,
        public array $featureFlags,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
