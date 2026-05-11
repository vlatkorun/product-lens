<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\ProcessSyncSchedule;

final readonly class ClaimedSyncJobsDto
{
    /** @param list<ClaimedSyncJobDto> $jobs */
    public function __construct(
        public array $jobs,
    ) {
    }
}
