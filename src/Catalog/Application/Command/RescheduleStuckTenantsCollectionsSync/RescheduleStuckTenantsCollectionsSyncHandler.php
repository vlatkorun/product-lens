<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\RescheduleStuckTenantsCollectionsSync;

use App\Catalog\Application\Command\StartSync\StartSyncCommand;
use App\Catalog\Domain\Repository\MonitoredCollectionSyncRepositoryInterface;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RescheduleStuckTenantsCollectionsSyncHandler
{
    public function __construct(
        private MonitoredCollectionSyncRepositoryInterface $syncJobRepository,
        private MessageBusInterface $commandBus,
        private int $stuckThresholdMinutes,
    ) {
    }

    public function __invoke(RescheduleStuckTenantsCollectionsSyncCommand $_command): void
    {
        $threshold = new StuckCollectionSyncThresholdDto($this->stuckThresholdMinutes);

        $cutoff = new \DateTimeImmutable(\sprintf('-%d minutes', $threshold->thresholdMinutes));

        $stuckJobs = $this->syncJobRepository->findStuckPending($cutoff);

        foreach ($stuckJobs as $job) {
            $this->commandBus->dispatch(
                new StartSyncCommand($job->id()->toRfc4122()),
                [new TenantStamp($job->tenantId())],
            );
        }
    }
}
