<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantsCollectionsSync;

use App\Catalog\Application\Command\StartSync\StartSyncCommand;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class ProcessTenantsCollectionsSyncHandler
{
    public function __construct(
        private TenantScopedMonitoredCollectionSyncClaimer $claimer,
        private MessageBusInterface $commandBus,
        private int $batchSize,
    ) {
    }

    public function __invoke(ProcessTenantsCollectionsSyncCommand $command): void
    {
        $jobs = $this->claimer->claim(new TenantCollectionSyncClaimCriteriaDto(
            tenantIds: $command->tenantIds,
            lastCollectionId: $command->lastCollectionId,
            batchSize: $this->batchSize,
            now: new \DateTimeImmutable(),
        ));

        if ($jobs === []) {
            return;
        }

        foreach ($jobs as $job) {
            $this->commandBus->dispatch(
                new StartSyncCommand($job->syncJobId),
                [new TenantStamp(UuidV7::fromString($job->tenantId))],
            );
        }

        if (\count($jobs) === $this->batchSize) {
            $this->commandBus->dispatch(new ProcessTenantsCollectionsSyncCommand(
                tenantIds: $command->tenantIds,
                lastCollectionId: $jobs[\count($jobs) - 1]->monitoredCollectionId,
            ));
        }
    }
}
