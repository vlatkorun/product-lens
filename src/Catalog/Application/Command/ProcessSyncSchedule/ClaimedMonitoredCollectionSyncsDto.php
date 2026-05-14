<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessSyncSchedule;

final readonly class ClaimedMonitoredCollectionSyncsDto
{
    /** @param list<ClaimedMonitoredCollectionSyncDto> $jobs */
    public function __construct(
        public array $jobs,
    ) {
    }
}
