<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessSyncSchedule;

use App\Catalog\Application\Command\StartSync\StartSyncCommand;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class ProcessSyncScheduleHandler
{
    public function __construct(
        private MonitoredCollectionSyncClaimer $claimer,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(ProcessSyncScheduleCommand $_command): void
    {
        $result = $this->claimer->claim(new \DateTimeImmutable()); 

        foreach ($result->jobs as $job) {
            $this->commandBus->dispatch(
                new StartSyncCommand($job->syncJobId),
                [new TenantStamp(UuidV7::fromString($job->tenantId))],
            );
        }
    }
}
