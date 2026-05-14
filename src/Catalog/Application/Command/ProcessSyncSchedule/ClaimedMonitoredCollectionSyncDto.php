<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessSyncSchedule;

final readonly class ClaimedMonitoredCollectionSyncDto
{
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
    ) {
    }
}
