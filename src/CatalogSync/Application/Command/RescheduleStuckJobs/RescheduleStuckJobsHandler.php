<?php

declare(strict_types=1);

namespace App\CatalogSync\Application\Command\RescheduleStuckJobs;

use App\CatalogSync\Application\Command\StartSync\StartSyncCommand;
use App\CatalogSync\Domain\Repository\SyncJobRepositoryInterface;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RescheduleStuckJobsHandler
{
    private const STUCK_THRESHOLD_MINUTES = 5;

    public function __construct(
        private SyncJobRepositoryInterface $syncJobRepository,
        private MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(RescheduleStuckJobsCommand $command): void
    {
        $threshold = new \DateTimeImmutable(
            \sprintf('-%d minutes', self::STUCK_THRESHOLD_MINUTES),
        );

        $stuckJobs = $this->syncJobRepository->findStuckPending($threshold);

        foreach ($stuckJobs as $job) {
            $this->commandBus->dispatch(
                new StartSyncCommand($job->id()->toRfc4122()),
                [new TenantStamp($job->tenantId())],
            );
        }
    }
}
