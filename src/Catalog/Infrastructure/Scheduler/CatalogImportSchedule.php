<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Scheduler;

use App\Catalog\Application\Command\DispatchCollectionSyncBatch\DispatchCollectionSyncBatchCommand;
use App\Catalog\Application\Command\DispatchCollectionSyncBatch\DispatchCollectionSyncBatchCriteriaDto;
use App\Catalog\Application\Command\RescheduleStuckTenantsCollectionsSync\RescheduleStuckTenantsCollectionsSyncCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('catalog_import')]
final class CatalogImportSchedule implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= new Schedule()
            ->with(
                RecurringMessage::every('5 minutes', new DispatchCollectionSyncBatchCommand(new DispatchCollectionSyncBatchCriteriaDto())),
                RecurringMessage::every('2 minutes', new RescheduleStuckTenantsCollectionsSyncCommand()),
            );
    }
}
