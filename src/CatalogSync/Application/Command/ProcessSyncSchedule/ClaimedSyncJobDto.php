<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\ProcessSyncSchedule;

final readonly class ClaimedSyncJobDto
{
    public function __construct(
        public string $syncJobId,
        public string $tenantId,
    ) {
    }
}
