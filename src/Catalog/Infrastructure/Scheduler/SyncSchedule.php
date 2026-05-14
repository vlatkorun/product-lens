<?php

declare(strict_types=1);

namespace App\CatalogSync\Infrastructure\Scheduler;

use App\CatalogSync\Application\Command\ProcessSyncSchedule\ProcessSyncScheduleCommand;
use App\CatalogSync\Application\Command\RescheduleStuckJobs\RescheduleStuckJobsCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('catalog_sync')]
final class SyncSchedule implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= new Schedule()
            ->with(
                RecurringMessage::every('5 minutes', new ProcessSyncScheduleCommand()),
                RecurringMessage::every('2 minutes', new RescheduleStuckJobsCommand()),
            );
    }
}
