<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ProcessTenantsCollectionsSync;

use App\Catalog\Application\Claim\TenantCollectionSyncClaimCriteriaDto;
use App\Catalog\Application\Claim\TenantScopedMonitoredCollectionSyncClaimerInterface;
use App\Catalog\Application\Command\StartSync\StartSyncCommand;
use App\Shared\Infrastructure\Symfony\TenantStamp;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

#[AsMessageHandler]
final readonly class ProcessTenantsCollectionsSyncHandler
{
    public function __construct(
        private TenantScopedMonitoredCollectionSyncClaimerInterface $claimer,
        private MessageBusInterface $commandBus,
        private int $batchSize,
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
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
            $this->logger->info('No eligible collections found for tenant batch', [
                'tenant_count' => \count($command->tenantIds),
            ]);

            return;
        }

        $lastCollectionId = $jobs[\count($jobs) - 1]->monitoredCollectionId;

        $this->logger->info('Collection batch claimed', [
            'count' => \count($jobs),
            'tenant_count' => \count($command->tenantIds),
            'last_collection_id' => $lastCollectionId,
        ]);

        foreach ($jobs as $job) {
            $this->commandBus->dispatch(
                new StartSyncCommand($job->syncJobId),
                [new TenantStamp(UuidV7::fromString($job->tenantId))],
            );
        }

        if (\count($jobs) === $this->batchSize) {
            $this->commandBus->dispatch(new ProcessTenantsCollectionsSyncCommand(
                tenantIds: $command->tenantIds,
                lastCollectionId: $lastCollectionId,
            ));
        }
    }
}
