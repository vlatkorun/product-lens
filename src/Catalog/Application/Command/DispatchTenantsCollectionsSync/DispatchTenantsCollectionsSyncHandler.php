<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchTenantsCollectionsSync;

use App\Catalog\Application\Claim\ActiveTenantBatchClaimerInterface;
use App\Catalog\Application\Claim\Dto\ActiveTenantBatchCriteriaDto;
use App\Catalog\Application\Command\AcquireTenantsCollectionsForSync\AcquireTenantsCollectionsForSyncCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DispatchTenantsCollectionsSyncHandler
{
    public function __construct(
        private ActiveTenantBatchClaimerInterface $claimer,
        private MessageBusInterface $commandBus,
        private int $batchSize,
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchTenantsCollectionsSyncCommand $command): void
    {
        if ($command->criteria->tenantIds !== []) {
            $this->logger->info('Dispatching targeted tenant sync', [
                'tenant_count' => \count($command->criteria->tenantIds),
            ]);

            $this->commandBus->dispatch(new AcquireTenantsCollectionsForSyncCommand($command->criteria->tenantIds));

            return;
        }

        $tenantIds = $this->claimer->claim(new ActiveTenantBatchCriteriaDto($command->criteria->lastTenantId, $this->batchSize));

        if ($tenantIds === []) {
            $this->logger->info('No more active tenants, iteration complete');

            return;
        }

        $lastTenantId = $tenantIds[\count($tenantIds) - 1];

        $this->logger->info('Active tenant batch claimed', [
            'count'          => \count($tenantIds),
            'last_tenant_id' => $lastTenantId,
        ]);

        $this->commandBus->dispatch(new AcquireTenantsCollectionsForSyncCommand($tenantIds));

        if (\count($tenantIds) === $this->batchSize) {
            $this->commandBus->dispatch(new DispatchTenantsCollectionsSyncCommand(
                new DispatchTenantsCollectionsSyncCriteriaDto(lastTenantId: $lastTenantId),
            ));
        }
    }
}
