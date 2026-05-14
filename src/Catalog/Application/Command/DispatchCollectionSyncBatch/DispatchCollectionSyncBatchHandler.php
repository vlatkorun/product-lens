<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DispatchCollectionSyncBatch;

use App\Catalog\Application\Command\ProcessTenantsCollectionsSync\ProcessTenantsCollectionsSyncCommand;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class DispatchCollectionSyncBatchHandler
{
    public function __construct(
        private ActiveTenantBatchClaimer $claimer,
        private MessageBusInterface $commandBus,
        private int $batchSize,
        #[Autowire(service: 'monolog.logger.catalog_import')]
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(DispatchCollectionSyncBatchCommand $command): void
    {
        if ($command->criteria->tenantIds !== []) {
            $this->logger->info('Dispatching targeted tenant sync', [
                'tenant_count' => \count($command->criteria->tenantIds),
            ]);

            $this->commandBus->dispatch(new ProcessTenantsCollectionsSyncCommand($command->criteria->tenantIds));

            return;
        }

        $tenantIds = $this->claimer->claim(new ActiveTenantBatchCriteriaDto($command->criteria->lastTenantId, $this->batchSize));

        if ($tenantIds === []) {
            $this->logger->info('No more active tenants, iteration complete');

            return;
        }

        $lastTenantId = $tenantIds[\count($tenantIds) - 1];

        $this->logger->info('Active tenant batch claimed', [
            'count' => \count($tenantIds),
            'last_tenant_id' => $lastTenantId,
        ]);

        $this->commandBus->dispatch(new ProcessTenantsCollectionsSyncCommand($tenantIds));

        if (\count($tenantIds) === $this->batchSize) {
            $this->commandBus->dispatch(new DispatchCollectionSyncBatchCommand(
                new DispatchCollectionSyncBatchCriteriaDto(lastTenantId: $lastTenantId),
            ));
        }
    }
}
